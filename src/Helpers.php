<?php

namespace Balsama\Dealth;

class Helpers
{

    public static function writeToCsv($headers, $data, $fileName, $filePath = __DIR__ . '/../data/')
    {
        $fp = fopen($filePath . $fileName, 'w');
        fputcsv($fp, $headers);
        foreach ($data as $datum) {
            fputcsv($fp, $datum);
        }
        fclose($fp);
    }

    public static function includeArrayKeysInArray(array $array): array
    {
        $newArray = [];
        foreach ($array as $key => $row) {
            if (is_array($row)) {
                array_unshift($row, $key);
                $newArray[$key] = $row;
            } elseif (is_string($row) || is_int($row)) {
                $newArray[$key] = [$key, $row];
            } else {
                throw new \InvalidArgumentException('Expected each row in the array to be an array, string, or int.');
            }
        }

        return $newArray;
    }

    public static function exportToJson($data, $fileName = 'commits.json', $filePath = __DIR__ . '/../data/')
    {
        $data = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($filePath . $fileName, $data);
    }

}