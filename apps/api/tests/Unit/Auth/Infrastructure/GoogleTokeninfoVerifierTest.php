<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure;

use App\Auth\Exception\GoogleAuthenticationFailedException;
use App\Auth\Infrastructure\GoogleTokeninfoVerifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleTokeninfoVerifierTest extends TestCase
{
    #[Test]
    public function verify_id_token_valide_renvoie_google_user_info(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'sub' => 'google-sub-123',
            'aud' => 'allowed-client-id.apps.googleusercontent.com',
            'email' => 'user@example.com',
            'email_verified' => 'true',
            'name' => 'Test User',
        ]) ?: '', ['http_code' => 200]));

        $verifier = new GoogleTokeninfoVerifier($httpClient, 'allowed-client-id.apps.googleusercontent.com');

        $info = $verifier->verify('valid-id-token');

        self::assertSame('google-sub-123', $info->sub);
        self::assertSame('user@example.com', $info->email);
        self::assertTrue($info->emailVerified);
        self::assertSame('Test User', $info->name);
    }

    #[Test]
    public function verify_audience_non_listee_throw(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'sub' => 'google-sub-123',
            'aud' => 'someone-elses-client-id',
            'email' => 'user@example.com',
            'email_verified' => true,
        ]) ?: '', ['http_code' => 200]));

        $verifier = new GoogleTokeninfoVerifier($httpClient, 'allowed-client-id');

        $this->expectException(GoogleAuthenticationFailedException::class);
        $this->expectExceptionMessage('Audience');

        $verifier->verify('valid-id-token');
    }

    #[Test]
    public function verify_csv_audiences_accepte_plusieurs_client_ids(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'sub' => 'sub',
            'aud' => 'ios-client-id',
            'email' => 'u@e.com',
            'email_verified' => true,
        ]) ?: '', ['http_code' => 200]));

        $verifier = new GoogleTokeninfoVerifier($httpClient, 'web-client-id, ios-client-id, android-client-id');
        $info = $verifier->verify('valid');

        self::assertSame('sub', $info->sub);
    }

    #[Test]
    public function verify_id_token_vide_throw(): void
    {
        $verifier = new GoogleTokeninfoVerifier(new MockHttpClient(), 'aud');

        $this->expectException(GoogleAuthenticationFailedException::class);
        $verifier->verify('');
    }

    #[Test]
    public function verify_response_non_200_throw(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{}', ['http_code' => 400]));
        $verifier = new GoogleTokeninfoVerifier($httpClient, 'aud');

        $this->expectException(GoogleAuthenticationFailedException::class);
        $verifier->verify('invalid');
    }

    #[Test]
    public function verify_sans_aucune_audience_whitelisted_throw_pour_tout_token(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(json_encode([
            'sub' => 'sub',
            'aud' => 'whatever',
            'email' => 'u@e.com',
            'email_verified' => true,
        ]) ?: '', ['http_code' => 200]));

        $verifier = new GoogleTokeninfoVerifier($httpClient, '');

        $this->expectException(GoogleAuthenticationFailedException::class);
        $verifier->verify('any');
    }
}
