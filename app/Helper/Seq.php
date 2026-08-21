<?php

namespace App\Helper;

use App\Models\Sequence;

class Seq
{
    public static function no()
    {
        $sequence = Sequence::find(1);
        $sequence->update([
            'sequence_no' => $sequence->sequence_no + 1,
        ]);
        return $sequence->sequence_no;
    }

    public static function uuidV4()
    {
        // Generate 16 random bytes (128 bits)
        $data = random_bytes(16);

        // Set the version to 4 (0100 in binary)
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

        // Set the variant to RFC 4122 (10xx in binary)
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        // Convert binary data to hexadecimal string
        $hex = bin2hex($data);

        // Format the UUID (8-4-4-4-12 format)
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),  // First 8 characters
            substr($hex, 8, 4),  // Next 4 characters
            substr($hex, 12, 4), // Next 4 characters
            substr($hex, 16, 4), // Next 4 characters
            substr($hex, 20, 12) // Remaining 12 characters
        );
    }
}
