<?php

declare(strict_types=1);

namespace Tests\Infrastructure;

use App\Infrastructure\FedaPay\SignatureWebhook;
use PHPUnit\Framework\TestCase;

final class SignatureWebhookTest extends TestCase
{
    private const SECRET = 'wh_sandbox_secret';
    private const CORPS = '{"name":"transaction.approved","entity":{"id":42}}';

    private function entete(int $t, string $secret = self::SECRET): string
    {
        return "t=$t,s=" . hash_hmac('sha256', $t . '.' . self::CORPS, $secret);
    }

    public function testSignatureValide(): void
    {
        $maintenant = 1_800_000_000;
        self::assertTrue((new SignatureWebhook(self::SECRET))->estValide(self::CORPS, $this->entete($maintenant), $maintenant));
    }

    public function testMauvaisSecretOuCorpsModifie(): void
    {
        $maintenant = 1_800_000_000;
        $verificateur = new SignatureWebhook(self::SECRET);

        self::assertFalse($verificateur->estValide(self::CORPS, $this->entete($maintenant, 'autre'), $maintenant));
        self::assertFalse($verificateur->estValide(self::CORPS . ' ', $this->entete($maintenant), $maintenant));
    }

    public function testSignatureTropAncienneRefusee(): void
    {
        $maintenant = 1_800_000_000;
        self::assertFalse((new SignatureWebhook(self::SECRET))->estValide(self::CORPS, $this->entete($maintenant - 600), $maintenant));
    }

    public function testEnteteAbsentOuSecretNonConfigure(): void
    {
        self::assertFalse((new SignatureWebhook(self::SECRET))->estValide(self::CORPS, null));
        self::assertFalse((new SignatureWebhook(''))->estValide(self::CORPS, $this->entete(time()), time()));
    }
}
