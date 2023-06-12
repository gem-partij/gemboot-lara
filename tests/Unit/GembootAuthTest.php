<?php

namespace Gemboot\Tests\Unit;

use Gemboot\Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;

class GembootAuthTest extends TestCase
{

    protected $user_success = 'tester';
    protected $pass_success = 'tester';

    /** ================
     * TEST LOGIN TANPA KIRIM REQUEST APAPUN
     *
     * @test
     **/
    public function test_login_without_request()
    {
        $response = $this->postJson('/auth/login');
        // ob_get_clean();

        $response
            ->assertStatus(400)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST LOGIN SALAH USER
     *
     * @test
     **/
    public function test_login_wrong_cred()
    {
        $response = $this->postJson('/auth/login', [
            'npp' => '123',
            'password' => '123',
            'hwid' => 'gemboot',
        ]);
        // ob_get_clean();

        $response
            ->assertStatus(400)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST LOGIN SUCCESS
     *
     * @test
     **/
    public function test_login_success()
    {
        $response = $this->postJson('/auth/login', [
            'npp' => $this->user_success,
            'password' => $this->pass_success,
            'hwid' => 'gemboot',
        ]);
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST VALIDATE TOKEN INVALID
     *
     * @test
     **/
    public function test_validate_token_invalid()
    {
        $response = $this->getJson('/auth/validate-token');
        // ob_get_clean();

        $response
            ->assertStatus(401);
        // ->assertJson(fn (AssertableJson $json) =>
        //     $json
        //     ->has('status')
        //     ->has('message')
        //     ->has('data')
        // );
    }

    /** ================
     * TEST VALIDATE TOKEN SUCCESS
     *
     * @test
     **/
    public function test_validate_token_success()
    {
        $response_auth = $this->postJson('/auth/login', [
            'npp' => $this->user_success,
            'password' => $this->pass_success,
            'hwid' => 'gemboot',
        ]);

        $auth_data = $response_auth->getData()->data;

        $response = $this->withHeaders([
            'Authorization' => $auth_data->token_type . ' ' . $auth_data->access_token,
        ])->getJson('/auth/validate-token');
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST GET ME
     *
     * @test
     **/
    public function test_get_me_success()
    {
        $response_auth = $this->postJson('/auth/login', [
            'npp' => $this->user_success,
            'password' => $this->pass_success,
            'hwid' => 'gemboot',
        ]);

        $auth_data = $response_auth->getData()->data;

        $response = $this->withHeaders([
            'Authorization' => $auth_data->token_type . ' ' . $auth_data->access_token,
        ])->getJson('/auth/me');
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST POST LOGOUT
     *
     * @test
     **/
    public function test_post_logout_success()
    {
        $response_auth = $this->postJson('/auth/login', [
            'npp' => $this->user_success,
            'password' => $this->pass_success,
            'hwid' => 'gemboot',
        ]);

        $auth_data = $response_auth->getData()->data;

        $response = $this->withHeaders([
            'Authorization' => $auth_data->token_type . ' ' . $auth_data->access_token,
        ])->postJson('/auth/logout');
        // ob_get_clean();

        $response
            ->assertStatus(200)
            ->assertJson(
                fn (AssertableJson $json) =>
                $json
                    ->has('status')
                    ->has('message')
                    ->has('data')
            );
    }

    /** ================
     * TEST GET HAS ROLE (FORBIDDEN)
     *
     * @test
     **/
    // public function test_get_has_role_forbidden()
    // {
    //     $response_auth = $this->postJson('/auth/login', [
    //         'npp' => 'tester',
    //         'password' => 'tester',
    //         'hwid' => 'gemboot',
    //     ]);

    //     $auth_data = $response_auth->getData()->data;

    //     $response = $this->withHeaders([
    //         'Authorization' => $auth_data->token_type . ' ' . $auth_data->access_token,
    //     ])->getJson('/auth/has-role', [
    //         'role_name' => 'tester',
    //     ]);
    //     // ob_get_clean();

    //     $response
    //         ->assertStatus(403)
    //         ->assertJson(
    //             fn (AssertableJson $json) =>
    //             $json
    //                 ->has('status')
    //                 ->has('message')
    //                 ->has('data')
    //         );
    // }
}
