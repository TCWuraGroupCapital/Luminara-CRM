<?php

use Illuminate\Support\Arr;
use Webkul\Ledger\Support\CanonicalJson;
use Webkul\Ledger\Support\LedgerSigner;
use Webkul\Ledger\Support\MerkleTree;

/**
 * Return an ES256 (NIST P-256 / prime256v1) keypair PEM tuple that works even when Windows OPENSSL_CONF is unset.
 *
 * @return array{private_pem: string, public_pem: string}
 */
function ledgerTestEs256Keypair(): array
{
    $key = @openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);

    if ($key !== false) {
        $privatePem = '';

        if (@openssl_pkey_export($key, $privatePem)) {
            $details = openssl_pkey_get_details($key);

            if (is_array($details) && isset($details['key']) && is_string($details['key'])) {
                return [
                    'private_pem' => $privatePem,
                    'public_pem' => $details['key'],
                ];
            }
        }
    }

    // RFC 7515 Appendix A.3 deterministic P-256 test vector fallback
    $x = (string) LedgerSigner::base64UrlDecode('f83OJ3D2xF1Bg8vub9tLe1gHMzV76e8Tus9uPHvRVEU');
    $y = (string) LedgerSigner::base64UrlDecode('x_FEzRu9m36HLN_tue659LNpXW6pCyStikYjKIWI5a0');
    $d = (string) LedgerSigner::base64UrlDecode('jpsQnnGQmL-YBIffH1136cspYG6-0iY7X1fCE9-E9LI');
    $point = "\x04".$x.$y;

    $spkiDer = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00".$point;
    $sec1Der = "\x30\x77\x02\x01\x01\x04\x20".$d."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00".$point;

    return [
        'private_pem' => "-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($sec1Der), 64, "\n")."-----END EC PRIVATE KEY-----\n",
        'public_pem' => "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spkiDer), 64, "\n")."-----END PUBLIC KEY-----\n",
    ];
}

it('encodes RFC 8785 JCS deterministic JSON across key permutations, lists, floats, and empty maps', function () {
    $first = [
        'zeta' => 'https://luminara.example/quotes/42',
        'alpha' => [
            'b' => 2,
            'a' => 1,
            'unicode' => 'Pinot Noir — Côte d’Or',
        ],
        'items' => [
            ['sku' => 'WINE-2', 'qty' => 3],
            ['qty' => 1, 'sku' => 'WINE-1'],
        ],
        'empty_list' => [],
        'empty_map' => (object) [],
        'empty_array_object' => new ArrayObject,
        'amount' => 180.0,
    ];

    $second = [
        'amount' => 180.0,
        'empty_array_object' => new ArrayObject,
        'empty_map' => new stdClass,
        'empty_list' => [],
        'items' => [
            ['qty' => 3, 'sku' => 'WINE-2'],
            ['sku' => 'WINE-1', 'qty' => 1],
        ],
        'alpha' => [
            'unicode' => 'Pinot Noir — Côte d’Or',
            'a' => 1,
            'b' => 2,
        ],
        'zeta' => 'https://luminara.example/quotes/42',
    ];

    $jcsFirst = CanonicalJson::encode($first);
    $jcsSecond = CanonicalJson::encode($second);

    expect($jcsFirst)->toBe($jcsSecond)
        ->and($jcsFirst)->toContain('"empty_list":[]')
        ->and($jcsFirst)->toContain('"empty_map":{}')
        ->and($jcsFirst)->toContain('"empty_array_object":{}')
        ->and($jcsFirst)->toContain('"amount":180.0')
        ->and($jcsFirst)->toContain('"https://luminara.example/quotes/42"')
        ->and($jcsFirst)->toContain('Côte d’Or');
});

it('recursively scrubs sensitive secret keys while preserving domain fields', function () {
    $dirty = [
        'title' => 'Enterprise Wine Allocation',
        'user_password' => 'SuperSecret!123',
        'webhook_secret' => 'whsec_999',
        'nested' => [
            'bearer_token' => 'tok_live_abc',
            'x_api_key' => 'ak_12345',
            'signing_private_key' => 'priv_hex',
            'recovery_codes_list' => ['1111-2222', '3333-4444'],
            'safe_amount' => 4200,
        ],
        'headers' => [
            ['Authorization' => 'Bearer secret-jwt', 'Accept' => 'application/json'],
        ],
    ];

    $scrubbed = CanonicalJson::scrubSecrets($dirty);

    expect($scrubbed['title'])->toBe('Enterprise Wine Allocation')
        ->and($scrubbed['user_password'])->toBe('[REDACTED]')
        ->and($scrubbed['webhook_secret'])->toBe('[REDACTED]')
        ->and($scrubbed['nested']['bearer_token'])->toBe('[REDACTED]')
        ->and($scrubbed['nested']['x_api_key'])->toBe('[REDACTED]')
        ->and($scrubbed['nested']['signing_private_key'])->toBe('[REDACTED]')
        ->and($scrubbed['nested']['recovery_codes_list'])->toBe('[REDACTED]')
        ->and($scrubbed['nested']['safe_amount'])->toBe(4200)
        ->and($scrubbed['headers'][0]['Authorization'])->toBe('[REDACTED]')
        ->and($scrubbed['headers'][0]['Accept'])->toBe('application/json');
});

it('signs and verifies Ed25519 and ES256 (both ASN.1 DER and 64-byte IEEE P1363 WebAuthn) proofs', function () {
    $hexSeed = str_repeat('42', 32);
    $signerA = new LedgerSigner($hexSeed);
    $signerB = new LedgerSigner($hexSeed);
    $otherSigner = new LedgerSigner(str_repeat('99', 32));

    $digest = hash('sha256', 'luminara-block-1');
    $signed = $signerA->signHash($digest);

    expect($signed['alg'])->toBe('ed25519')
        ->and($signed['pubkey'])->toBe($signerB->publicKeyBase64Url())
        ->and($signerB->verify('ed25519', $signed['pubkey'], $digest, $signed['proof']))->toBeTrue()
        ->and($signerB->verify('ed25519', $signed['pubkey'], hash('sha256', 'tampered'), $signed['proof']))->toBeFalse()
        ->and($signerB->verify('ed25519', $otherSigner->publicKeyBase64Url(), $digest, $signed['proof']))->toBeFalse();

    // ES256 DER + P1363 verification
    $es256 = ledgerTestEs256Keypair();
    $privKey = openssl_pkey_get_private($es256['private_pem']);
    expect($privKey)->not->toBeFalse();

    $derSignature = '';
    $ok = openssl_sign($digest, $derSignature, $privKey, OPENSSL_ALGO_SHA256);
    expect($ok)->toBeTrue();

    $derProofB64 = LedgerSigner::base64UrlEncode($derSignature);
    $p1363Raw = LedgerSigner::derToP1363($derSignature);

    expect($p1363Raw)->not->toBeNull()
        ->and(strlen((string) $p1363Raw))->toBe(64);

    $p1363ProofB64 = LedgerSigner::base64UrlEncode((string) $p1363Raw);
    $b64PemPubkey = LedgerSigner::base64UrlEncode($es256['public_pem']);

    expect($signerA->verify('es256', $es256['public_pem'], $digest, $derProofB64))->toBeTrue()
        ->and($signerA->verify('es256', $es256['public_pem'], $digest, $p1363ProofB64))->toBeTrue()
        ->and($signerA->verify('es256', $b64PemPubkey, $digest, $p1363ProofB64))->toBeTrue()
        ->and($signerA->verify('es256', $es256['public_pem'], $digest.'-tampered', $p1363ProofB64))->toBeFalse();

    // Flip one bit in P1363 signature -> must fail verification
    $corruptedRaw = (string) $p1363Raw;
    $corruptedRaw[15] = chr(ord($corruptedRaw[15]) ^ 0x01);
    $corruptedProof = LedgerSigner::base64UrlEncode($corruptedRaw);

    expect($signerA->verify('es256', $es256['public_pem'], $digest, $corruptedProof))->toBeFalse();
});

it('builds and verifies RFC 6962 domain-separated Merkle proofs across 1, 2, 3, 7, and 64 leaves and blocks second-preimage attacks', function (int $leafCount) {
    $leaves = [];

    for ($i = 0; $i < $leafCount; $i++) {
        $leaves[] = hash('sha256', "block-leaf-{$leafCount}-{$i}");
    }

    $root = MerkleTree::root($leaves);
    expect($root)->toHaveLength(64)->not->toBe(MerkleTree::EMPTY_ROOT);

    for ($index = 0; $index < $leafCount; $index++) {
        $proof = MerkleTree::proof($leaves, $index);

        expect(MerkleTree::verifyProof($leaves[$index], $proof, $root))->toBeTrue();
        expect(MerkleTree::verifyProof(hash('sha256', 'forged-leaf'), $proof, $root))->toBeFalse();

        if ($proof !== []) {
            $tamperedDirection = $proof;
            $tamperedDirection[0]['direction'] = $tamperedDirection[0]['direction'] === 'L' ? 'R' : 'L';
            expect(MerkleTree::verifyProof($leaves[$index], $tamperedDirection, $root))->toBeFalse();

            $tamperedSibling = $proof;
            $tamperedSibling[0]['sibling'] = hash('sha256', 'forged-sibling');
            expect(MerkleTree::verifyProof($leaves[$index], $tamperedSibling, $root))->toBeFalse();
        }
    }

    // Second-preimage domain-separation test: passing root itself as a fake single leaf must fail
    expect(MerkleTree::verifyProof($root, [], $root))->toBeFalse();
})->with([1, 2, 3, 7, 64]);

it('returns 64 zeroes for an empty Merkle tree and rejects empty-root proofs', function () {
    expect(MerkleTree::root([]))->toBe(str_repeat('0', 64))
        ->and(MerkleTree::verifyProof(hash('sha256', 'x'), [], str_repeat('0', 64)))->toBeFalse();
});

it('maintains 10-locale translation key parity across Webkul\\Ledger language files', function () {
    $baseDir = dirname(__DIR__, 3).'/packages/Webkul/Ledger/src/Resources/lang';
    $locales = ['ar', 'en', 'es', 'fa', 'ja', 'ko', 'pt_BR', 'tr', 'vi', 'zh_CN'];
    $enKeys = array_keys(Arr::dot(require "{$baseDir}/en/app.php"));

    expect($enKeys)->not->toBeEmpty();

    foreach ($locales as $locale) {
        $file = "{$baseDir}/{$locale}/app.php";
        expect(is_file($file))->toBeTrue("Missing Ledger lang file for locale [{$locale}]");

        $map = Arr::dot(require $file);

        foreach ($enKeys as $key) {
            expect($map)->toHaveKey($key)
                ->and($map[$key])->toBeString()->not->toBeEmpty();
        }
    }
});
