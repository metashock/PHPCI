<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2013, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         http://www.phptesting.org/
*/

namespace PHPCI\Model\Build;

use PHPCI\Model\Build;
use PHPCI\Builder;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
* Local Build Model
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Core
*/
class LocalBuild extends Build
{
    /**
    * Create a working copy by cloning, copying, or similar.
    */
    public function createWorkingCopy(Builder $builder, $buildPath)
    {
        $reference  = $this->getProject()->getReference();
        $reference  = substr($reference, -1) == '/' ? substr($reference, 0, -1) : $reference;
        $buildPath  = substr($buildPath, 0, -1);

        // If there's a /config file in the reference directory, it is probably a bare repository
        // which we'll extract into our build path directly.
        if (is_file($reference.'/config') && $this->handleBareRepository($builder, $reference, $buildPath) === true) {
            return true;
        }

        $buildSettings = $this->handleConfig($builder, $reference);

        if ($buildSettings === false) {
            return false;
        }

        if (isset($buildSettings['prefer_symlink']) && $buildSettings['prefer_symlink'] === true) {
            return $this->handleSymlink($builder, $reference, $buildPath);
        } else {
            $builder->executeCommand('cp -Rf "%s" "%s/"', $reference, $buildPath);
        }

        return true;
    }

    protected function handleBareRepository(Builder $builder, $reference, $buildPath)
    {
        $gitConfig = parse_ini_file($reference.'/config', true);

        // If it is indeed a bare repository, then extract it into our build path:
        if ($gitConfig['core']['bare']) {
            $builder->executeCommand('git --git-dir="%s" archive master | tar -x -C "%s"', $reference, $buildPath);
            return true;
        }

        return false;
    }

    protected function handleSymlink(Builder $builder, $reference, $buildPath)
    {
        if (is_link($buildPath) && is_file($buildPath)) {
            unlink($buildPath);
        }

        $builder->log(sprintf('Symlinking: %s to %s', $reference, $buildPath));

        if (!symlink($reference, $buildPath)) {
            $builder->logFailure('Failed to symlink.');
            return false;
        }

        return true;
    }

    protected function handleConfig(Builder $builder, $reference)
    {
        /** @todo Add support for database-based yml definition */
        if (!is_file($reference . '/phpci.yml')) {
            $builder->logFailure('Project does not contain a phpci.yml file.');
            return false;
        }

        $yamlParser = new YamlParser();
        $yamlFile = file_get_contents($reference . '/phpci.yml');
        $builder->setConfigArray($yamlParser->parse($yamlFile));
        return $builder->getConfig('build_settings');
    }
}
