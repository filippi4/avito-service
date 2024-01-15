<?php

namespace App\Services\Google;


use App\Services\Google\Exceptions\GoogleServiceException;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Sheets;
use App\Services\Google\Exceptions\GoogleSpreadsheetException;

class GoogleClient
{
    protected Google_Client $client;

    protected $drive = null;

    public function __construct()
    {
        $this->buildClient();

        $this->authenticate();
    }

    private function buildClient()
    {
        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . base_path() . '/service-credentials.json');
        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->setApplicationName(config('services.google.application_name'));
        $client->setScopes([Google_Service_Sheets::DRIVE, Google_Service_Sheets::SPREADSHEETS]);
        $client->setAccessType('offline');

        $this->client = $client;

        $this->drive = new Google_Service_Drive($this->client);
    }

    public function authenticate()
    {
        $this->client->fetchAccessTokenWithAssertion();
    }

    public function accessToken()
    {
        return $this->client->getAccessToken();
    }

    public function validate($filename)
    {
        try {
            $file = collect($this->driveListFiles())->transform(function ($item) {
                return $item->toSimpleObject();
            })->where('name', $filename)->first();

            if (empty($file)) {
                throw new GoogleSpreadsheetException('Неверные параметры Google таблицы');
            }
        } catch (\Throwable $th) {
            if (!$th instanceof GoogleSpreadsheetException) {
                throw new GoogleServiceException($th->getMessage(), $th->getCode());
            }

            throw $th;
        }

        return $file;
    }

    public function drive()
    {
        return $this->drive;
    }

    public function driveListFiles()
    {
        return $this->drive->files->listFiles();
    }
}
