<?php

namespace Gemboot\Commands;

use Gemboot\Libraries\HttpClient;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\MissingInputException;

/**
 * Checks that an auth service answers the way Gemboot expects, endpoint by
 * endpoint, so a mismatch shows up before a deploy instead of as 401s or 403s
 * in production. Only reads: it never calls login or logout.
 */
class ContractTest extends Command
{
    protected $signature = 'gemboot:contract-test
                            {--url= : Base URL of the auth service (default: GEMBOOT_AUTH_BASE_API)}
                            {--token= : A valid token to test with; asked for when missing, so it stays out of your shell history}
                            {--role= : A role the token\'s user has, to check a "yes" answer}
                            {--permission= : A permission the token\'s user has, to check a "yes" answer}
                            {--json : Print the result as JSON}';

    protected $description = 'Check that an auth service answers the way Gemboot expects';

    /** Names nobody should have, for "no" answers. */
    private const UNKNOWN_ROLE = 'gemboot-contract-test-unknown-role';

    private const UNKNOWN_PERMISSION = 'gemboot-contract-test.unknown-permission';

    private const INVALID_TOKEN = 'gemboot-contract-test-invalid-token';

    /** @var array<int, array{check: string, status: string, detail: string}> */
    private array $results = [];

    private string $baseUrl = '';

    public function handle(): int
    {
        // Laravel reuses the command object within one process.
        $this->results = [];

        $url = $this->option('url') ?: config('gemboot.auth.base_api');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('No valid auth service URL. Pass --url= or set GEMBOOT_AUTH_BASE_API.');

            return self::FAILURE;
        }
        $this->baseUrl = rtrim($url, '/') . '/';

        $token = $this->token();

        if ($this->checkRejections()) {
            if ($token === null) {
                $this->record('Checks with a valid token', 'skip', 'No token given. Pass --token= or run the command interactively.');
            } else {
                $this->checkMe($token);
                $this->checkValidateToken($token);
                $this->checkHasRole($token);
                $this->checkHasPermissionTo($token);
            }
        }

        return $this->report();
    }

    private function token(): ?string
    {
        $token = $this->option('token');
        if (($token === null || $token === '') && $this->input->isInteractive() && !$this->option('json')) {
            try {
                $token = $this->secret('A valid token to test with (input is hidden; leave empty to skip)');
            } catch (MissingInputException) {
                // No one to answer, e.g. Artisan::call() from code.
                $token = null;
            }
        }

        $token = trim((string) $token);
        // Accept the token with or without its scheme.
        $token = preg_replace('/^Bearer\s+/i', '', $token);

        return $token === '' ? null : $token;
    }

    /**
     * Requests without a token or with an invalid one must not get a 200.
     * Returns false when the service can't be reached, so the rest is skipped.
     */
    private function checkRejections(): bool
    {
        $without = $this->fetch('me', null);
        if ($without->code === 0) {
            $this->record('Reach the auth service', 'fail', 'No connection: ' . ($without->error ?? 'unknown error') . '. Check the URL, the network, and GEMBOOT_HTTP_VERIFY.');

            return false;
        }

        $this->expectRejected('me without a token', $without);
        $this->expectRejected('me with an invalid token', $this->fetch('me', self::INVALID_TOKEN));
        $this->expectRejected('validate-token with an invalid token', $this->fetch('validate-token', self::INVALID_TOKEN));

        return true;
    }

    private function expectRejected(string $check, object $response): void
    {
        if ($response->code === 200) {
            $this->record($check, 'fail', 'Answered 200. Gemboot treats 200 as "valid", so this would let anyone in.');
        } elseif ($response->code >= 500) {
            $this->record($check, 'fail', "Answered {$response->code}. Gemboot treats 5xx as an outage and answers 503 instead of 401.");
        } else {
            $this->record($check, 'pass', "Rejected with {$response->code}.");
        }
    }

    private function checkMe(string $token): void
    {
        $response = $this->fetch('me', $token);
        if ($response->code !== 200) {
            $this->record('me with the token', 'fail', "Answered {$response->code}, expected 200. Is the token valid? token-validated would answer 401 for it.");

            return;
        }

        $user = $response->payload;
        if (!is_array($user) || $user === []) {
            $this->record('me with the token', 'fail', 'Answered 200 without a user. token-validated needs a non-empty object (a "data" wrapper is fine).');

            return;
        }

        $this->record('me with the token', 'pass', 'Answered 200 with a user (fields: ' . implode(', ', array_slice(array_keys($user), 0, 8)) . ').');

        if (array_key_exists('id', $user) || is_array($user['user'] ?? null) && array_key_exists('id', $user['user'])) {
            $this->record('me has a user id', 'pass', 'Found "id"' . (array_key_exists('id', $user) ? '.' : ' under "user".'));
        } else {
            $this->record('me has a user id', 'warn', 'No "id" (or "user.id"). With the gemboot guard, auth()->id() will be null.');
        }
    }

    private function checkValidateToken(string $token): void
    {
        $code = $this->fetch('validate-token', $token)->code;

        // token-validated:client only looks at the status code.
        if ($code === 200) {
            $this->record('validate-token with the token', 'pass', 'Answered 200. Gemboot only reads the status code here, so the body may have any shape.');
        } else {
            $this->record('validate-token with the token', 'fail', "Answered {$code}, expected 200. token-validated:client would reject the token.");
        }
    }

    private function checkHasRole(string $token): void
    {
        $response = $this->fetch('has-role', $token, ['role_name' => self::UNKNOWN_ROLE]);
        $hasRole = $this->booleanField('has-role', $response, 'has_role', 'role:');
        if ($hasRole === null) {
            return;
        }

        $this->record('has-role for a role nobody has', $hasRole ? 'fail' : 'pass', $hasRole
            ? 'Answered has_role: true. role: would let every user in.'
            : 'Answered has_role: false.');

        $role = $this->option('role');
        if (!$role) {
            $this->record('has-role for a role the user has', 'skip', 'Pass --role= with one of the user\'s roles to check a "yes".');

            return;
        }

        $yes = $this->booleanField('has-role', $this->fetch('has-role', $token, ['role_name' => $role]), 'has_role', 'role:');
        $this->record("has-role for \"{$role}\"", $yes ? 'pass' : 'fail', $yes
            ? 'Answered has_role: true.'
            : 'Answered has_role: false. Does the user really have this role?');

        // "a|b" means any of them, for role: and GembootPermission::hasRole().
        $any = $this->booleanField('has-role', $this->fetch('has-role', $token, ['role_name' => $role . '|' . self::UNKNOWN_ROLE]), 'has_role', 'role:');
        if ($any !== null) {
            $this->record('has-role with "a|b"', $any ? 'pass' : 'warn', $any
                ? 'Treats "a|b" as either one, as Gemboot expects.'
                : 'Answered false for "' . $role . '|other". Gemboot expects "a|b" to mean either one (role:admin|editor).');
        }
    }

    private function checkHasPermissionTo(string $token): void
    {
        $response = $this->fetch('has-permission-to', $token, ['permission_name' => self::UNKNOWN_PERMISSION]);
        $has = $this->booleanField('has-permission-to', $response, 'has_permission_to', 'permission:');
        if ($has === null) {
            return;
        }

        $this->record('has-permission-to for a permission nobody has', $has ? 'fail' : 'pass', $has
            ? 'Answered has_permission_to: true. permission: would let every user in.'
            : 'Answered has_permission_to: false.');

        $permission = $this->option('permission');
        if (!$permission) {
            $this->record('has-permission-to for a permission the user has', 'skip', 'Pass --permission= with one of the user\'s permissions to check a "yes".');

            return;
        }

        $yes = $this->booleanField('has-permission-to', $this->fetch('has-permission-to', $token, ['permission_name' => $permission]), 'has_permission_to', 'permission:');
        $this->record("has-permission-to for \"{$permission}\"", $yes ? 'pass' : 'fail', $yes
            ? 'Answered has_permission_to: true.'
            : 'Answered has_permission_to: false. Does the user really have this permission?');

        // "a|b": has_permission_to means all of them, has_any_permission any of them.
        $both = $this->fetch('has-permission-to', $token, ['permission_name' => $permission . '|' . self::UNKNOWN_PERMISSION]);
        $all = $this->booleanField('has-permission-to', $both, 'has_permission_to', 'permission:');
        if ($all !== null) {
            $this->record('has-permission-to with "a|b"', $all ? 'warn' : 'pass', $all
                ? 'Answered has_permission_to: true for "' . $permission . '|other". Gemboot\'s docs say "a|b" needs both, so permission:a|b would let in users with only one.'
                : 'Treats "a|b" as both, as Gemboot expects.');
        }

        $any = is_array($both->payload) ? ($both->payload['has_any_permission'] ?? null) : null;
        $this->record('has-permission-to has has_any_permission', $any === true ? 'pass' : 'warn', $any === true
            ? 'Answered has_any_permission: true for "' . $permission . '|other".'
            : 'No has_any_permission: true for "' . $permission . '|other". GembootPermission::hasPermissionTo([...]) with an array reads it.');
    }

    /**
     * The boolean a check reads, or null (recorded as a failure) when the answer
     * isn't a 200 with that field.
     */
    private function booleanField(string $endpoint, object $response, string $field, string $middleware): ?bool
    {
        if ($response->code !== 200) {
            $this->record("{$endpoint} answers", 'fail', "Answered {$response->code}, expected 200 with \"{$field}\". {$middleware} would answer 403 for everyone.");

            return null;
        }

        $value = is_array($response->payload) ? ($response->payload[$field] ?? null) : null;
        if (!is_bool($value)) {
            $this->record("{$endpoint} answers", 'fail', "No boolean \"{$field}\" in the answer (a \"data\" wrapper is fine). {$middleware} would answer 403 for everyone.");

            return null;
        }

        return $value;
    }

    /**
     * One GET to the auth service. The token is sent but never printed.
     *
     * @return object{code: int, payload: mixed, error: ?string}
     */
    private function fetch(string $endpoint, ?string $token, array $query = []): object
    {
        $client = (new HttpClient($this->baseUrl))->withHeaders(['Accept' => 'application/json']);
        if ($token !== null) {
            $client->setToken('Bearer ' . $token);
        }

        $response = $client->get($endpoint, $query);
        $body = $response->data ?? null;

        return (object) [
            'code' => (int) ($response->info->http_code ?? 0),
            // Like AuthLibrary: a "data" wrapper is optional.
            'payload' => is_array($body) && array_key_exists('data', $body) ? $body['data'] : $body,
            'error' => $response->error ?? null,
        ];
    }

    private function record(string $check, string $status, string $detail): void
    {
        $this->results[] = ['check' => $check, 'status' => $status, 'detail' => $detail];
    }

    private function report(): int
    {
        $count = fn (string $status) => count(array_filter($this->results, fn ($r) => $r['status'] === $status));
        $failed = $count('fail');

        if ($this->option('json')) {
            $this->line(json_encode([
                'url' => $this->baseUrl,
                'checks' => $this->results,
                'failed' => $failed,
                'warnings' => $count('warn'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->line("<options=bold>Auth service contract test</> ({$this->baseUrl})");
        $this->newLine();

        $icons = ['pass' => '<info>✓</info>', 'fail' => '<fg=red>✗</>', 'warn' => '<fg=yellow>!</>', 'skip' => '<fg=gray>-</>'];
        foreach ($this->results as $result) {
            $this->line("  {$icons[$result['status']]} {$result['check']}");
            $this->line("      <fg=gray>{$result['detail']}</>");
        }

        $this->newLine();
        $this->line('<fg=gray>login and logout are not called: logout would end the session of the token you passed.</>');

        if ($failed > 0) {
            $this->line("<fg=red>{$failed} check(s) failed, {$count('warn')} warning(s).</>");

            return self::FAILURE;
        }

        $this->line($count('warn') > 0
            ? "<fg=yellow>No failures, {$count('warn')} warning(s).</>"
            : '<info>The auth service answers the way Gemboot expects.</info>');

        return self::SUCCESS;
    }
}
