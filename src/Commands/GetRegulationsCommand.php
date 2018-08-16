<?php

namespace Upaid\Regulations\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Upaid\Regulations\Services\CommandService as Service;

/**
 * GetRegulationsCommand class
 *
 * This class gets the requested file from remote (or local) server
 * and updates this file in application.
 *
 * Example usage:
 * To update regulations in english file just call in console:
 *
 * php artisan regulations:get http://your_remote_server_url/en.html
 *
 * @package  Regulations
 * @author   Cezary Strąk <cezary.strak@upaid.pl>
 */
class GetRegulationsCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update regulations.';

    /**
     * Command service
     *
     * @var Service
     */
    public $cfgService;

    /**
     * GetRegulationsCommand constructor.
     *
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->cfgService = $service;
        parent::__construct();
    }

    /**
     * Configure command
     */
    protected function configure()
    {
        $this
            ->setName('regulations:get {param}')
            ->setDescription('Get regulations from remote server.')
            ->setAliases(['getRegulations'])
            ->setDefinition(
                [new InputArgument('param', InputArgument::OPTIONAL),]
            );
    }

    /**
     * Executes the console command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return mixed
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $answer = null;
        $param = $input->getArgument('param');

        if (empty($param)) {
            $answer = $this->ask('Define regulations server url');
        }

        $url = $answer ? $answer : $param;

        $exploded = explode('/', $url);
        $urlFilename = end($exploded);
        $urlAppName = prev($exploded);

        $output->writeln('Trying to get ' . $urlFilename . ' from: ' . $url);
        $this->getRegulations($url, $output, $urlFilename, $urlAppName);

    }

    /**
     * Get regulations
     *
     * @param                 $url
     * @param OutputInterface $output
     * @param                 $fileName
     *
     * @return mixed
     */
    private function getRegulations($url, OutputInterface $output, $fileName)
    {
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            $data = curl_exec($curl);

            if (curl_errno($curl)) {
                $output->writeln(
                    'Curl error: ' . curl_errno($curl)
                    . '. Check http://php.net/manual/en/function.curl-errno.php for more information.'
                );
                curl_close($curl);
                return;
            }

            if(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200){
                $output->writeln('File not found');
                curl_close($curl);
                return;
            }


            $output->writeln('Regulations downloaded');
            $output->writeln('Creating backup...');
            $backupResponse = $this->createBackup($fileName);
            switch ($backupResponse['code']) {
                case 0:
                    $output->writeln(
                        'File ' . $fileName . ' already backuped!'
                    );
                    break;
                case 1:
                    $output->writeln(
                        'Backup created successfully!'
                    );
                    break;
                case 2:
                    $output->writeln('Failed to create backup!');
                    break;
            }

            $output->writeln('Updating ' . $fileName . ' file...');

            $message = $this->updateFile($fileName, $data)
                ? 'File update success!'
                : 'Failed to update ' . $fileName . ' file!';

            $output->writeln($message);

            curl_close($curl);
        } catch (\Exception $ex) {
            $output->writeln('Failed to get content of ' . $fileName . ' file from: ' . $url);
            $output->writeln($ex->getMessage());
        }
    }

    /**
     * Updates downloaded regulations data to requested file
     *
     * @param $fileName
     * @param $data
     *
     * @return bool
     */
    private function updateFile($fileName, $data)
    {
        $filePath = $this->cfgService->getFilePath($fileName);
        if(preg_match('/[\/\\\\]/', $filePath)) {
            $this->createDirIfNotExists($this->getDirPath($filePath));
        }
        $handler = fopen($filePath, 'w+');
        return fwrite($handler, $data) !== false;
    }

    /**
     * Get dir path from file path
     *
     * @param $filePath string Path to file
     *
     * @return string
     */
    private function getDirPath($filePath) {
        $delimiter = strpos($filePath, '/') !== false ? '/' : '\\';
        $arrayOfDirs = explode($delimiter, $filePath);
        if(count($arrayOfDirs) > 2) {
            array_pop($arrayOfDirs);
            return implode($delimiter, $arrayOfDirs);
        }
        return $filePath;
    }

    /**
     * Create directory if not exists
     *
     * @param $dir
     */
    private function createDirIfNotExists($dir) {
        if(!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Creates backup of file
     *
     * @param $fileName
     *
     * @return array :
     * 'code' => 0 | File already backuped. Original file and backuped fila are the same. No need to backup it again.
     * 'code' => 1 | Backup created successfully!
     * 'code' => 2 | Failed to create backup!
     */
    private function createBackup($fileName)
    {
        $filePath = Service::REGULATIONS_DIRECTORY . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($filePath)) {
            return ['code' => 2];
        }
        $fileExist = file_exists(Service::BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . $fileName);

        $isEqual = (is_dir(Service::BACKUP_DIRECTORY) && $fileExist) ? md5(file_get_contents($filePath)) === md5(file_get_contents(Service::BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . $fileName)) : false;

        if (!$isEqual) {
            $return = ($this->cfgService->makeBcDir() && copy($filePath, Service::BACKUP_DIRECTORY . DIRECTORY_SEPARATOR . $fileName)) ?
                ['code' => 1] :
                ['code' => 2];
        } else {
            $return = ['code' => 0];
        }
        return $return;
    }
}
