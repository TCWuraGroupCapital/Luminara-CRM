<?php

namespace Webkul\Ledger\Support;

use InvalidArgumentException;
use OutOfBoundsException;

class MerkleTree
{
    public const EMPTY_ROOT = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Compute an RFC 6962 leaf hash: SHA-256(0x00 || raw_block_hash).
     */
    public static function leafHash(string $blockHashHex): string
    {
        $raw = self::decodeHexHash($blockHashHex);

        return hash('sha256', "\x00".$raw);
    }

    /**
     * Compute an RFC 6962 interior node hash: SHA-256(0x01 || left || right).
     */
    public static function nodeHash(string $leftHex, string $rightHex): string
    {
        $leftRaw = self::decodeHexHash($leftHex);
        $rightRaw = self::decodeHexHash($rightHex);

        return hash('sha256', "\x01".$leftRaw.$rightRaw);
    }

    /**
     * Compute the RFC 6962 binary Merkle tree root for an ordered list of 64-character hex block hashes.
     *
     * @param  array<int, string>  $blockHashes
     */
    public static function root(array $blockHashes): string
    {
        $hashes = array_values($blockHashes);

        if ($hashes === []) {
            return self::EMPTY_ROOT;
        }

        $level = array_map([self::class, 'leafHash'], $hashes);

        while (count($level) > 1) {
            $nextLevel = [];
            $count = count($level);

            for ($i = 0; $i < $count; $i += 2) {
                if ($i + 1 < $count) {
                    $nextLevel[] = self::nodeHash($level[$i], $level[$i + 1]);
                } else {
                    $nextLevel[] = $level[$i];
                }
            }

            $level = $nextLevel;
        }

        return $level[0];
    }

    /**
     * Build the RFC 6962 inclusion proof path for leaf $index.
     *
     * @param  array<int, string>  $blockHashes
     * @return list<array{direction: 'L'|'R', sibling: string}>
     */
    public static function proof(array $blockHashes, int $index): array
    {
        $hashes = array_values($blockHashes);
        $total = count($hashes);

        if ($index < 0 || $index >= $total) {
            throw new OutOfBoundsException("Merkle leaf index {$index} is out of bounds for {$total} leaves.");
        }

        $level = array_map([self::class, 'leafHash'], $hashes);
        $path = [];
        $currentIndex = $index;

        while (count($level) > 1) {
            $count = count($level);

            if ($currentIndex % 2 === 0) {
                if ($currentIndex + 1 < $count) {
                    $path[] = [
                        'direction' => 'R',
                        'sibling' => $level[$currentIndex + 1],
                    ];
                }
            } else {
                $path[] = [
                    'direction' => 'L',
                    'sibling' => $level[$currentIndex - 1],
                ];
            }

            $nextLevel = [];

            for ($i = 0; $i < $count; $i += 2) {
                if ($i + 1 < $count) {
                    $nextLevel[] = self::nodeHash($level[$i], $level[$i + 1]);
                } else {
                    $nextLevel[] = $level[$i];
                }
            }

            $level = $nextLevel;
            $currentIndex = intdiv($currentIndex, 2);
        }

        return $path;
    }

    /**
     * Verify an RFC 6962 Merkle inclusion proof against $expectedRoot.
     *
     * @param  array<int, mixed>  $proof
     */
    public static function verifyProof(string $blockHash, array $proof, string $expectedRoot): bool
    {
        if (! self::isValidHexHash($blockHash) || ! self::isValidHexHash($expectedRoot)) {
            return false;
        }

        if (strtolower($expectedRoot) === self::EMPTY_ROOT) {
            return false;
        }

        $current = self::leafHash($blockHash);

        foreach ($proof as $step) {
            if (! is_array($step)) {
                return false;
            }

            $direction = $step['direction'] ?? null;
            $sibling = $step['sibling'] ?? null;

            if (! is_string($sibling) || ! self::isValidHexHash($sibling)) {
                return false;
            }

            if ($direction === 'L') {
                $current = self::nodeHash($sibling, $current);
            } elseif ($direction === 'R') {
                $current = self::nodeHash($current, $sibling);
            } else {
                return false;
            }
        }

        return hash_equals(strtolower($expectedRoot), $current);
    }

    private static function isValidHexHash(string $hex): bool
    {
        return strlen($hex) === 64 && ctype_xdigit($hex);
    }

    private static function decodeHexHash(string $hex): string
    {
        if (! self::isValidHexHash($hex)) {
            throw new InvalidArgumentException('Merkle node input must be a 64-character hexadecimal SHA-256 hash.');
        }

        /** @var string $raw */
        $raw = hex2bin($hex);

        return $raw;
    }
}
