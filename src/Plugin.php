<?php
/**
 * This file is part of the "jakoch/phantomjs-installer" package.
 *
 * Copyright (c) 2013-2017 Jens-André Koch <jakoch@web.de>
 *
 * The content is released under the MIT License. Please view
 * the LICENSE file that was distributed with this source code.
 */
namespace Vaimo\PhantomInstaller;

use Composer\Composer;
use Composer\Config;
use Composer\Downloader\TransportException;
use Composer\IO\BaseIO as IO;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Package\Version\VersionParser;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Repository\RepositoryInterface;

class Plugin implements \Composer\Plugin\PluginInterface, \Composer\EventDispatcher\EventSubscriberInterface
{
    const PHANTOMJS_CDNURL_DEFAULT = 'https://api.bitbucket.org/2.0/repositories/ariya/phantomjs/downloads/';
    const OS_TYPE_UNKNOWN = 'unknown';

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IO
     */
    protected $io;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var \Composer\Util\Filesystem
     */
    protected $fileSystem;

    /**
     * @var \Composer\Package\CompletePackage
     */
    protected $ownerPackage;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->config = $this->composer->getConfig();

        $this->fileSystem = new \Composer\Util\Filesystem();

        $this->cache = new \Composer\Cache(
            $this->io,
            implode(DIRECTORY_SEPARATOR, array(
                $this->config->get('cache-dir'),
                'files',
                'phantomjs-installer',
                'downloaded-bin'
            ))
        );
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => 'installPhantomJs',
            ScriptEvents::POST_UPDATE_CMD => 'installPhantomJs',
        );
    }

    public function getInstalledVersion()
    {
        $binDir = $this->config->get('bin-dir');

        if (!$binPath = realpath($binDir . DIRECTORY_SEPARATOR . $this->getFileName())) {
            return false;
        }

        return $this->getPhantomJsVersionFromBinary($binPath);
    }

    public function installPhantomJs(Event $event)
    {
        if ($this->getOS() === self::OS_TYPE_UNKNOWN) {
            $this->io->error('PhantomJs installation skipped: failed to determine the OS type');
            return;
        }

        $requestedVersion = $this->getRequestedVersion();
        $installedVersion = $this->getInstalledVersion();

        $requestedVersion = '2.1.1';

        if ($installedVersion && version_compare($requestedVersion, $installedVersion) !== 1) {
            return;
        }

        $this->io->write(sprintf('<info>Installing PhantomJs v%s</info>', $requestedVersion));

        $this->cache->clear();
        $downloadDir = rtrim($this->cache->getRoot(), DIRECTORY_SEPARATOR);

        if (!$package = $this->downloadRelease($requestedVersion, $downloadDir)) {
            return;
        }

        if ($this->installBinary($package, $this->config->get('bin-dir'))) {
            $this->io->write(sprintf('<info>Done</info>', $requestedVersion));
        }
    }

    public function getPhantomJsVersionFromBinary($pathToBinary)
    {
        try {
            exec(escapeshellarg($pathToBinary) . ' -v', $stdout);

            return reset($stdout);
        } catch (\Exception $e) {
            $this->io->warning("Caught exception while checking PhantomJS version:\n" . $e->getMessage());
            $this->io->notice('Re-downloading PhantomJS');
        }

        return false;
    }

    public function getFileName()
    {
        $name = 'phantomjs';

        return $this->getOS() === 'windows' ? $name . '.exe' : $name;
    }

    public function downloadRelease($version, $targetDir)
    {
        $downloadManager = $this->composer->getDownloadManager();
        $versions = $this->getVersionQueue($version);

        while ($version = array_shift($versions)) {
            if (isset($package)) {
                $this->io->warning(sprintf('Failed to donwload requested version, retrying: %s', $version));
            }

            $package = $this->createComposerInMemoryPackage($version, $targetDir);

            try {
                $downloader = $downloadManager->getDownloaderForInstalledPackage($package);
                $downloader->download($package, $targetDir, false);
                $this->io->write('');

                return $package;
            } catch (TransportException $e) {
                if ($e->getStatusCode() === 404) {
                    continue;
                }

                $this->io->error(sprintf(
                    PHP_EOL . '<error>Transport failure %s while downloading v%s: %s</error>',
                    $e->getStatusCode(),
                    $version,
                    $e->getMessage()
                ));

                break;
            } catch (\Exception $e) {
                $this->io->error(sprintf(
                    PHP_EOL . '<error>Unexpected error while downloading v%s: %s</error>',
                    $version,
                    $e->getMessage()
                ));

                break;
            }
        }

        $this->io->error(
            PHP_EOL . '<error>Failed to download PhantomJs</error>'
        );

        return false;
    }

    public function createComposerInMemoryPackage($version, $targetDir)
    {
        $remoteFile = $this->getURL($version);

        $versionParser = new VersionParser();

        $package = new \Composer\Package\Package(
            'phantomjs-binary',
            $versionParser->normalize($version),
            $version
        );

        $package->setBinaries(array($this->getFileName()));

        $package->setInstallationSource('dist');
        $package->setDistType(pathinfo($remoteFile, PATHINFO_EXTENSION) === 'zip' ? 'zip' : 'tar');

        $package->setTargetDir($targetDir);
        $package->setDistUrl($remoteFile);

        return $package;
    }

    public function getVersionQueue($version = null)
    {
        $list = array('2.1.1', '2.0.0', '1.9.8', '1.9.7');

        if (!$version) {
            return $list;
        }

        return array_merge(
            [$version],
            array_filter($list, function ($item) use ($version) {
                return version_compare($item, $version, '<');
            })
        );
    }

    public function getOwnerPackage()
    {
        if ($this->ownerPackage === null) {
            $repository = $this->composer->getRepositoryManager()->getLocalRepository();

            foreach ($repository->getCanonicalPackages() as $package) {
                if ($package->getType() !== 'composer-plugin') {
                    continue;
                }

                $autoload = $package->getAutoload();

                if (!isset($autoload['psr-4'])) {
                    continue;
                }

                $matches = array_filter(array_keys($autoload['psr-4']), function ($item) {
                    return strpos(__NAMESPACE__, rtrim($item, '\\')) === 0;
                });

                if (!$matches) {
                    continue;
                }

                $this->ownerPackage = $package;

                break;
            }
        }

        if (!$this->ownerPackage) {
            throw new \Exception('Failed to detect the plugin package');
        }

        return $this->ownerPackage;
    }

    public function getPackageVersion($package)
    {
        $packageName = $package->getName();
        $version = $package->getPrettyVersion();

        if (strpos($version, 'dev-') !== 0) {
            return $version;
        }

        foreach ($this->composer->getLocker()->getAliases() as $idx => $alias) {
            if ($alias['package'] !== $packageName) {
                continue;
            }

            return $alias['alias'];
        }

        // fallback to the hardcoded latest version, if "dev-master" was set
        if ($version === 'dev-master') {
            $versions = $this->getVersionQueue();

            return reset($versions);
        }
    }

    public function getRequestedVersion()
    {
        $version = $this->getPackageVersion($this->getOwnerPackage());

        return implode('.', array_pad(array_slice(explode('.', $version), 0, 3), 3, '0'));
    }

    public function installBinary(\Composer\Package\Package $package, $binDir)
    {
        $sourceDir = $package->getTargetDir();
        $sourceDir = file_exists(DIRECTORY_SEPARATOR . $sourceDir) ? DIRECTORY_SEPARATOR . $sourceDir : $sourceDir;

        $matches = array();
        foreach ($package->getBinaries() as $binary) {
            $globPattern = $sourceDir . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . $binary;

            $matches = array_merge(
                $matches,
                $this->recursiveGlob($globPattern)
            );
        }

        if (!$matches) {
            $this->io->error(sprintf('Could not locate the binary "%s" from downloaded source', $file));
        }

        $package->setBinaries(array_map(function ($path) use ($sourceDir) {
            return ltrim(substr($path, strlen($sourceDir)), DIRECTORY_SEPARATOR);
        }, $matches));

        $binaryInstaller = new \Composer\Installer\BinaryInstaller($this->io, $binDir, 'full');
        $binaryInstaller->installBinaries($package, $sourceDir);

        return $package->getBinaries();
    }

    function recursiveGlob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge(
                $files,
                $this->recursiveGlob($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags)
            );
        }

        return $files;
    }

    public function getRemoteFileName($version)
    {
        $os = $this->getOS();

        $template = '';

        if ($os === 'windows') {
            $template = 'phantomjs-{{VERSION}}-windows.zip';
        }

        if ($os === 'linux') {
            $bitsize = $this->getBitSize();

            if ($bitsize === '32') {
                $template = 'phantomjs-{{VERSION}}-linux-i686.tar.bz2';
            }

            if ($bitsize === '64') {
                $template = 'phantomjs-{{VERSION}}-linux-x86_64.tar.bz2';
            }
        }

        if ($os === 'macosx') {
            $file = 'phantomjs-{{VERSION}}-macosx.zip';
        }

        return str_replace('{{VERSION}}', $version, $template);
    }

    public function getURL($version)
    {
        $cdnUrl = $this->getCdnUrl($version);

        if (!$file = $this->getRemoteFileName($version)) {
            throw new \RuntimeException(
                'The Installer could not select a PhantomJS package for this OS.
                Please install PhantomJS manually into the /bin folder of your project.'
            );
        }

        return $cdnUrl . $file;
    }

    public function getCdnUrl($version)
    {
        $url = '';
        $extraData = $this->composer->getPackage()->getExtra();

        if (isset($_ENV['PHANTOMJS_CDNURL'])) {
            $url = $_ENV['PHANTOMJS_CDNURL'];
        } elseif (isset($_SERVER['PHANTOMJS_CDNURL'])) {
            $url = $_SERVER['PHANTOMJS_CDNURL'];
        } elseif (isset($extraData['phantomjs-installer']['cdnurl'])) {
            $url = $extraData['phantomjs-installer']['cdnurl'];
        }

        if ($url == '') {
            $url = static::PHANTOMJS_CDNURL_DEFAULT;
        }

        $url = rtrim($url, '/') . '/';

        if (preg_match('|github.com/medium/phantomjs/$|', strtolower($url))) {
            $url .= 'releases/download/v' . $version;
        }

        return rtrim($url, '/') . '/';
    }

    public function getOS()
    {
        if (isset($_ENV['PHANTOMJS_PLATFORM'])) {
            return strtolower($_ENV['PHANTOMJS_PLATFORM']);
        }

        if (isset($_SERVER['PHANTOMJS_PLATFORM'])) {
            return strtolower($_SERVER['PHANTOMJS_PLATFORM']);
        }

        $uname = strtolower(php_uname());

        if (strpos($uname, 'darwin') !== false ||
            strpos($uname, 'openbsd') !== false ||
            strpos($uname, 'freebsd') !== false
        ) {
            return 'macosx';
        } elseif (strpos($uname, 'win') !== false) {
            return 'windows';
        } elseif (strpos($uname, 'linux') !== false) {
            return 'linux';
        } else {
            return self::OS_TYPE_UNKNOWN;
        }
    }

    public function getBitSize()
    {
        if (isset($_ENV['PHANTOMJS_BITSIZE'])) {
            return strtolower($_ENV['PHANTOMJS_BITSIZE']);
        }

        if (isset($_SERVER['PHANTOMJS_BITSIZE'])) {
            return strtolower($_SERVER['PHANTOMJS_BITSIZE']);
        }

        if (PHP_INT_SIZE === 4) {
            return '32';
        }

        if (PHP_INT_SIZE === 8) {
            return '64';
        }

        return (string)PHP_INT_SIZE;
    }
}
