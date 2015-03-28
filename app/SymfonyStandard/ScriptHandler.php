<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */
namespace SymfonyStandard;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class ScriptHandler
{
    private static $options = array(
        'symfony-app-dir' => 'app',
        'symfony-web-dir' => 'web',
        'symfony-assets-install' => 'hard',
        'symfony-cache-warmup' => false,
    );
    
    protected static function getOptions(Event $event)
    {
        $options = array_merge(self::$options, $event->getComposer()->getPackage()->getExtra());

        $options['symfony-assets-install'] = getenv('SYMFONY_ASSETS_INSTALL') ?: $options['symfony-assets-install'];

        $options['process-timeout'] = $event->getComposer()->getConfig()->get('process-timeout');

        return $options;
    }

    public static function cleanUp(Event $event)
    {
        if (!$event->getIO()->askConfirmation('Would you like remove files: LICENSE, UPGRADE*.md, CHANGELOG*.md? [y/N] ', false)) {
            return;
        }
        $options = self::getOptions($event);
        $fs = new Filesystem();

        try {
            $projectDir = $options['symfony-app-dir'].'/../';
            $licenseFile = array($projectDir.'/LICENSE');
            $upgradeFiles = glob($projectDir.'/UPGRADE*.md');
            $changelogFiles = glob($projectDir.'/CHANGELOG*.md');

            $filesToRemove = array_merge($licenseFile, $upgradeFiles, $changelogFiles);
            $fs->remove($filesToRemove);
        } catch (\Exception $e) {
            // don't throw an exception in case any of the Symfony-related files cannot
            // be removed, because this is just an enhancement, not something mandatory
            // for the project
        }
    }

    protected static function projectName()
    {
        return basename(getcwd());
    }

    public static function updateParameters(Event $event)
    {
        $options = self::getOptions($event);
        $filename = $options['symfony-app-dir'].'/config/parameters.yml.dist';

        if (!is_writable($filename)) {
            if ($event->getIO()->isVerbose()) {
                $event->getIO()->write(sprintf(
                    " <comment>[WARNING]</comment> The value of the <info>secret</info> configuration option cannot be updated because\n".
                    " the <comment>%s</comment> file is not writable.\n",
                    $filename
                ));
            }
        }

        $hash = hash('sha1', uniqid(mt_rand(), true));
        if (function_exists('openssl_random_pseudo_bytes')) {
            $hash = hash('sha1', openssl_random_pseudo_bytes(23));
        }

        $ret = str_replace('ThisTokenIsNotSoSecretChangeIt', $hash, file_get_contents($filename));
        $ret = str_replace('project_db', self::projectName(), $ret);

        file_put_contents($filename, $ret);
    }

    public static function checkSymfonyRequirements(Event $event)
    {
        $getErrorMessage = function (\Requirement $requirement, $lineSize = 70){
            if ($requirement->isFulfilled()) {
                return;
            }

            $errorMessage  = wordwrap($requirement->getTestMessage(), $lineSize - 3, PHP_EOL.'   ').PHP_EOL;
            $errorMessage .= '   > '.wordwrap($requirement->getHelpText(), $lineSize - 5, PHP_EOL.'   > ').PHP_EOL;

            return $errorMessage;
        };

        $options = self::getOptions($event);
        require $options['symfony-app-dir'].'/SymfonyRequirements.php';
        $symfonyRequirements = new \SymfonyRequirements();
        $requirementsErrors = array();
        foreach ($symfonyRequirements->getRequirements() as $req) {
            if ($helpText = $getErrorMessage($req)) {
                $requirementsErrors[] = $helpText;
            }
        }

        $getInstallSymfonyVersion = function () use ($options){
            $composer = json_decode(file_get_contents($options['symfony-app-dir'].'/../composer.lock'), true);

            foreach ($composer['packages'] as $package) {
                if ('symfony/symfony' === $package['name']) {
                    if ('v' === substr($package['version'], 0, 1)) {
                        return substr($package['version'], 1);
                    };

                    return $package['version'];
                }
            }
        };

        if (empty($requirementsErrors)) {
            $event->getIO()->write(sprintf(
                " <info>%s</info>  Symfony %s was <info>successfully installed</info>. Now you can:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔',
                $getInstallSymfonyVersion()
            ));
        } else {
            $event->getIO()->write(sprintf(
                " <comment>%s</comment>  Symfony %s was <info>successfully installed</info> but your system doesn't meet its\n".
                "     technical requirements! Fix the following issues before executing\n".
                "     your Symfony application:\n",
                defined('PHP_WINDOWS_VERSION_BUILD') ? 'FAILED' : '✕',
                $getInstallSymfonyVersion()
            ));

            foreach ($requirementsErrors as $helpText) {
                $event->getIO()->write(' * '.$helpText);
            }

            $event->getIO()->write(sprintf(
                " After fixing these issues, re-check Symfony requirements executing this command:\n\n".
                "   <comment>php app/check.php</comment>\n\n".
                " Then, you can:\n"
            ));
        }

        $event->getIO()->write(sprintf(
            "    * Configure your application in <comment>app/config/parameters.yml</comment> file.\n\n".
            "    * Run your application:\n".
            "        1. Execute the <comment>php app/console server:run</comment> command.\n".
            "        2. Browse to the <comment>http://localhost:8000</comment> URL.\n\n".
            "    * Read the documentation at <comment>http://symfony.com/doc</comment>\n"
        ));
    }

    /**
     * Clears the Symfony cache.
     *
     * @param $event CommandEvent A instance
     */
    public static function createSchemaAndLoadFixtures(Event $event)
    {
        $options = self::getOptions($event);
        $consoleDir = $options['symfony-app-dir'];

        if (null === $consoleDir) {
            return;
        }

        static::executeCommand($event, $consoleDir, ' doctrine:database:create ', $options['process-timeout']);
        static::executeCommand($event, $consoleDir, ' doctrine:database:create -e=test ', $options['process-timeout']);
        static::executeCommand($event, $consoleDir, ' doctrine:schema:create ', $options['process-timeout']);
        static::executeCommand($event, $consoleDir, ' doctrine:schema:create -e=test ', $options['process-timeout']);
        static::executeCommand($event, $consoleDir, ' doctrine:fixtures:load ', $options['process-timeout']);
    }

    protected static function getPhp($includeArgs = true)
    {
        $phpFinder = new PhpExecutableFinder();
        if (!$phpPath = $phpFinder->find($includeArgs)) {
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    protected static function getPhpArguments()
    {
        $arguments = array();

        $phpFinder = new PhpExecutableFinder();
        if (method_exists($phpFinder, 'findArguments')) {
            $arguments = $phpFinder->findArguments();
        }

        if (false !== $ini = php_ini_loaded_file()) {
            $arguments[] = '--php-ini='.$ini;
        }

        return $arguments;
    }

    protected static function executeCommand(Event $event, $consoleDir, $cmd, $timeout = 300)
    {
        $php = escapeshellarg(self::getPhp(false));
        $phpArgs = implode(' ', array_map('escapeshellarg', self::getPhpArguments()));
        $console = escapeshellarg($consoleDir.'/console');
        if ($event->getIO()->isDecorated()) {
            $console .= ' --ansi';
        }

        $process = new Process($php.($phpArgs ? ' '.$phpArgs : '').' '.$console.' '.$cmd, null, null, null, $timeout);
        $process->run(function ($type, $buffer) use ($event) { $event->getIO()->write($buffer, false); });
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('An error occurred when executing the "%s" command.', escapeshellarg($cmd)));
        }
    }
} 