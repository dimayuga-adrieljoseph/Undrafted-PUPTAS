<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WaiverImport implements ToArray, WithHeadingRow
{
    /**
     * @param array $array
     */
    public function array(array $array)
    {
        // This is handled by Excel::toArray() which returns the array.
    }
}
