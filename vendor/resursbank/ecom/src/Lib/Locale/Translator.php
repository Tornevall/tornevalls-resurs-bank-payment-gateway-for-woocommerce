<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Locale;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Lib\Utilities\DataConverter;

use function file_get_contents;
use function json_decode;
use function is_string;

/**
 * Methods to extract locale specific phrases. The intention is to maintain
 * consistent terminology between implementations.
 *
 * @todo Check if ConfigException require test.
 */
class Translator
{
    /**
     * Path to the translations file that holds all translations in Ecom.
     *
     * @var string
     */
    private static string $translationsFilePath = __DIR__ . '/Resources/translations.json';

    /**
     * Key to store cached translations under.
     *
     * @var string
     */
    private static string $cacheKey = 'resursbank-ecom-translations';

    /**
     * Prevent object instantiation
     */
    private function __construct()
    {
    }

    /**
     * Loads translations file from disk, decodes the result into a collection
     * and returns that collection, and caches the resulting collection.
     *
     * @return PhraseCollection
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     */
    public static function load(): PhraseCollection
    {
        if (!file_exists(filename: self::$translationsFilePath)) {
            throw new FilesystemException(
                message: 'Translations file could not be found on path: ' .
                    self::$translationsFilePath,
                code: FilesystemException::CODE_FILE_MISSING
            );
        }

        $content = file_get_contents(filename: self::$translationsFilePath);

        if (!is_string(value: $content) || $content === '') {
            throw new FilesystemException(
                message: 'Translation file ' . self::$translationsFilePath .
                    ' is empty.',
                code: FilesystemException::CODE_FILE_EMPTY
            );
        }

        $result = self::decodeData(data: $content);

        Config::getCache()->write(
            key: self::$cacheKey,
            data: json_encode(
                value: $result->toArray(),
                flags: JSON_THROW_ON_ERROR
            ),
            ttl: 3600
        );

        return $result;
    }

    /**
     * Takes an english phrase and translates it to the language of the
     * configured locale.
     *
     * @param string $phraseId
     * @return string
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ConfigException
     * @see Config::$locale
     */
    public static function translate(string $phraseId): string
    {
        $phrases = self::getData();
        $result = null;

        /** @var Phrase $item */
        foreach ($phrases as $item) {
            if ($item->id === $phraseId) {
                /** @var string $result */
                $result = $item->translation->{Config::getLocale()->value};
            }
        }

        if ($result === null) {
            throw new TranslationException(
                message: "A translation with $phraseId could not be found."
            );
        }

        return $result;
    }

    /**
     * @return PhraseCollection
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     */
    public static function getData(): PhraseCollection
    {
        $cachedData = Config::getCache()->read(key: self::$cacheKey);

        return $cachedData === null
            ? self::load()
            : self::decodeData(data: $cachedData);
    }

    /**
     * Decodes JSON data into a collection of phrases.
     *
     * @param string $data
     * @return PhraseCollection
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     */
    public static function decodeData(string $data): PhraseCollection
    {
        /** @var array $decode */
        $decode = json_decode(
            json: $data,
            associative: false,
            depth: 512,
            flags: JSON_THROW_ON_ERROR
        );

        /** @var PhraseCollection $result */
        $result = DataConverter::arrayToCollection(
            data: $decode,
            targetType: Phrase::class,
        );

        return $result;
    }
}
