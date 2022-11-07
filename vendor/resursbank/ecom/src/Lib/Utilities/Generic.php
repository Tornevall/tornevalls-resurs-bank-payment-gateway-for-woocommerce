<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities;

use Exception;
use JsonException;
use ReflectionClass;
use ReflectionException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;

use function dirname;
use function is_object;
use function is_string;

/**
 * Generic Utils Class for things that is good to have.
 * @version 1.0.0
 *
 * @todo Add constructor with property promotion. PropertyNotSetInConstructor currently suppressed by psalm config.
 * @todo This class is overall very complex and should be refactored.
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Generic
{
    /**
     * Internal errorhandler.
     * @var callable|null
     */
    private $internalErrorHandler;

    /**
     * @var int
     */
    private int $internalExceptionCode;

    /**
     * Error message on internal handled errors, if any.
     * @var string
     */
    private string $internalExceptionMessage = '';

    /**
     * If open_basedir-warnings has been triggered once, we store that here.
     * @var bool
     * @todo We should use our FS classes instead to check for readability.
     */
    private bool $openBaseDirExceptionTriggered = false;

    /**
     * @var object
     */
    private object $composerData;

    /**
     * @var string
     */
    private string $composerLocation;

    /**
     * @param string $composerLocation
     * @return string
     * @throws Exception
     */
    public function getComposerVendor(string $composerLocation): string
    {
        return $this->getNameEntry(
            part: 'vendor',
            composerLocation: $composerLocation
        );
    }

    /**
     * @param string $part Defines which part of the vendor row you want (name or the vendor itself)
     * @param string $composerLocation Where composer.json are stored.
     * @return string
     * @throws Exception
     * @noinspection PhpSameParameterValueInspection
     */
    private function getNameEntry(string $part, string $composerLocation): string
    {
        $return = '';
        $composerNameEntry = explode(
            separator: '/',
            string: $this->getComposerTag(location: $composerLocation, tag: 'name'),
            limit: 2
        );

        switch ($part) {
            case 'name':
                if (isset($composerNameEntry[1])) {
                    $return = $composerNameEntry[1];
                }
                break;
            case 'vendor':
                if (isset($composerNameEntry[0])) {
                    $return = $composerNameEntry[0];
                }
                break;
            default:
        }

        return $return;
    }

    /**
     * Extract a tag from composer.json.
     *
     * @param string $location
     * @param string $tag
     * @return string
     * @throws Exception
     */
    public function getComposerTag(string $location, string $tag): string
    {
        $return = '';

        // @todo Object should be defined as stdClass or mor specific object.
        /** @psalm-suppress TypeDoesNotContainType */
        if (empty($this->composerData)) {
            $this->getComposerConfig(location: $location);
        }

        if (
            isset($this->composerData->{$tag}) &&
            is_string(value: $this->composerData->{$tag})
        ) {
            $return = (string) $this->composerData->{$tag};
        } elseif ($this->isOpenBaseDirException()) {
            $return = $this->getOpenBaseDirExceptionString();
        }

        return $return;
    }

    /**
     * @param string $location Location of composer.json.
     * @param int $maxDepth How deep the search for a composer.json will be. Usually you should not need more than 3.
     * @return string
     * @throws Exception
     *
     * @todo This method is too complex. Refactor it.
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getComposerConfig(string $location, int $maxDepth = 3): string
    {
        $this->setTemporaryInternalErrorHandler();

        if ($maxDepth > 3 || $maxDepth < 1) {
            $maxDepth = 3;
        }

        // Pre-check if file exists, to also make sure that open_basedir is not a problem.
        $locationCheck = file_exists(filename: $location);
        $this->isOpenBaseDirException();

        if (!$this->openBaseDirExceptionTriggered && !$locationCheck) {
            throw new FilesystemException(message: 'Invalid path', code: 1013);
        }
        if ($this->isOpenBaseDirException()) {
            return $this->getOpenBaseDirExceptionString();
        }
        if ($this->isOpenBaseDirException()) {
            return $this->getOpenBaseDirExceptionString();
        }
        $startAt = dirname(path: $location);
        if ($this->hasComposerFile(location: $startAt)) {
            $this->getComposerConfigData(location: $startAt);
            return $startAt;
        }

        $composerLocation = null;

        while ($maxDepth--) {
            $startAt .= '/..';
            if ($this->hasComposerFile(location: $startAt)) {
                $composerLocation = $startAt;
                break;
            }
        }

        if ($composerLocation === null) {
            throw new IllegalValueException(message: 'No composer.json found');
        }

        $this->getComposerConfigData(location: $composerLocation);

        return $this->composerLocation;
    }

    /**
     * Temporarily sets an error handler in an attempt to catch notice-level errors related to open_basedir
     */
    private function setTemporaryInternalErrorHandler(): void
    {
        if ($this->internalErrorHandler !== null) {
            restore_error_handler();
        }

        $this->internalErrorHandler = set_error_handler(
            callback: function ($errNo, $errStr) {
                if (empty($this->internalExceptionMessage)) {
                    $this->internalExceptionCode = $errNo;
                    $this->internalExceptionMessage = $errStr;
                }
                restore_error_handler();
                return $errNo === 2 && str_contains(haystack: $errStr, needle: 'open_basedir');
            },
            error_levels: E_WARNING
        );
    }

    /**
     * Checks internal warnings for open_basedir exceptions during runs.
     * @return bool
     */
    private function isOpenBaseDirException(): bool
    {
        // If triggered once, skip checks.
        if ($this->openBaseDirExceptionTriggered) {
            return $this->openBaseDirExceptionTriggered;
        }

        $return = $this->hasInternalException() &&
            $this->internalExceptionCode === 2 &&
            str_contains(
                haystack: $this->internalExceptionMessage,
                needle: 'open_basedir'
            );

        if ($return) {
            $this->openBaseDirExceptionTriggered = true;
        }

        return $return;
    }

    /**
     * @return bool
     */
    private function hasInternalException(): bool
    {
        return !empty($this->internalExceptionMessage);
    }

    /**
     * Exception string that is used in several places that will mark up if the running methods have
     * had problems with open_basedir security.
     * @return string
     */
    private function getOpenBaseDirExceptionString(): string
    {
        return 'open_basedir security active';
    }

    /**
     * @param string $location
     * @return bool
     */
    private function hasComposerFile(string $location): bool
    {
        $return = false;

        if (file_exists(filename: sprintf('%s/composer.json', $location))) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param string $location
     * @throws JsonException
     * @noinspection PhpMultipleClassDeclarationsInspection
     */
    private function getComposerConfigData(string $location): void
    {
        $this->composerLocation = $location;

        $getFrom = sprintf('%s/composer.json', $location);
        if (file_exists(filename: $getFrom)) {
            $data = null;
            $json = file_get_contents(
                filename: $getFrom
            );

            if ($json !== false && $json !== '') {
                /** @psalm-suppress MixedAssignment */
                $data = json_decode(
                    json: $json,
                    associative: false,
                    depth: 768,
                    flags: JSON_THROW_ON_ERROR
                );
            }

            if (is_object(value: $data)) {
                $this->composerData = $data;
            }
        }
    }

    /**
     * Using both class and composer.json to discover version (in case that composer.json are removed in a "final").
     *
     * @param string $composerLocation
     * @param int $composerDepth
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @throws Exception
     */
    public function getVersionByAny(
        string $composerLocation = '',
        int $composerDepth = 3,
        string $className = ''
    ): string {
        $return = '';

        $byComposer = $this->getVersionByComposer(
            location: $composerLocation,
            maxDepth: $composerDepth
        );
        $byClass = $this->getVersionByClassDoc(className: $className);

        // Composer always have higher priority.
        if (!empty($byComposer)) {
            $return = $byComposer;
        } elseif (!empty($byClass)) {
            $return = $byClass;
        }

        return $return;
    }

    /**
     * @param string $location
     * @param int $maxDepth Default is 3.
     * @return string
     * @throws Exception
     */
    public function getVersionByComposer(string $location, int $maxDepth = 3): string
    {
        $return = '';

        if (
            !empty(($this->getComposerConfig(location: $location, maxDepth: $maxDepth)))
            && !$this->isOpenBaseDirException()
        ) {
            $return = $this->getComposerTag(location: $this->composerLocation, tag: 'version');
        } elseif ($this->isOpenBaseDirException()) {
            $return = $this->getOpenBaseDirExceptionString();
        }

        return $return;
    }

    /**
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @throws IllegalValueException
     */
    public function getVersionByClassDoc(string $className = ''): string
    {
        return $this->getDocBlockItem(
            item: '@version',
            className: $className
        );
    }

    /**
     * @param string $item
     * @param string $functionName
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @throws IllegalValueException
     */
    public function getDocBlockItem(string $item, string $functionName = '', string $className = ''): string
    {
        return $this->getExtractedDocBlockItem(
            item: $item,
            doc: $this->getExtractedDocBlock(
                functionName: $functionName,
                className: $className
            )
        );
    }

    /**
     * @param string $item
     * @param string $doc
     * @return string
     */
    private function getExtractedDocBlockItem(string $item, string $doc): string
    {
        $return = '';

        if (!empty($doc)) {
            $docBlock = [];

            preg_match_all(
                pattern: sprintf('/%s\s(\w.+)\n/s', $item),
                subject: $doc,
                matches: $docBlock
            );

            if (isset($docBlock[1][0])) {
                $return = $docBlock[1][0];

                // Strip stuff after line breaks
                if (preg_match(pattern: '/[\n\r]/', subject: $return)) {
                    $multiRowData = preg_split(pattern: '/[\n\r]/', subject: $return);

                    if ($multiRowData !== false) {
                        $return = $multiRowData[0] ?? '';
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param string $functionName
     * @param string $className
     * @return string
     * @throws ReflectionException
     * @throws IllegalValueException
     */
    private function getExtractedDocBlock(
        string $functionName,
        string $className = ''
    ): string {
        if ($className === '') {
            $className = __CLASS__;
        }

        if (!class_exists(class: $className)) {
            throw new IllegalValueException(
                message: "Class $className does not exist"
            );
        }

        /** @psalm-suppress InvalidNamedArgument */
        $doc = new ReflectionClass(objectOrClass: $className);

        return $functionName === '' ?
            (string) $doc->getDocComment() :
            (string) $doc->getMethod(name: $functionName)->getDocComment();
    }
}
