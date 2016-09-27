<?php

namespace Whsuite\Translation;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Filesystem\Filesystem as File;
use Symfony\Component\Finder\Finder as Finder;

class Translation
{
    public $translator;
    public $phrases;
    public $file;
    public $lang;
    public $language; // Stores the language model

    /**
     * translation instance
     */
    protected static $instance;

    /**
     * Initiate
     *
     * Loads up the translation system for the selected language.
     * @param int $lang The language id to load
     */
    public function init($lang)
    {
        $language = \App::get('configs')->get('languages.' . $lang);

        if (! is_object($language)) {

            throw new \Exception('Language object not found');
        }

        $this->translator = new Translator($language->slug, new MessageSelector());
        $this->translator->setFallbackLocales(array('en'));
        $this->translator->addLoader('array', new ArrayLoader());

        $this->file = new File(); // Initiate the Symfony Filesystem

        $this->translator->addResource('array', $this->loadTranslations($language->slug), $language->slug);

        if ($language->slug != 'en') {
            $this->translator->addResource('array', $this->loadTranslations('en'), 'en');
        }

        $this->lang = $lang; // We set this so that we can re-run init from inside the purge method.

        // Check for App and add to the view
        if (\App::check('view')) {

            \App::get('view')->set('lang', $this);
        }
    }

    /**
     * Get Phrase
     *
     * Returns the requested phrase for the loaded translation.
     * @param  string $key Phrase key
     * @return string Phrase translation
     */
    public function get($key)
    {
        $language = \App::get('configs')->get('languages.' . $this->lang);
        if (! is_object($language)) {

            throw new \Exception('Language object not found');
        }

        if (! empty($this->phrases[$language->slug][$key])) {
            return $this->phrases[$language->slug][$key];
        }

        return $this->translator->trans($key, array(), null, $language->slug);
    }

    /**
     * Purge Cache
     *
     * This purges the translation cache files, and forces them to be regenerated from the database.
     */
    public function purge()
    {
        $translation_directory = STORAGE_DIR. DS . 'translations' . DS; // Set the translations cache directory
        $filesystem = new File(); // Initiate Symfony Filesystem
        $finder = new Finder(); // Initiate Symfony Finder
        $finder->files()->in($translation_directory); // Find all files within the translation directory
        $files = array(); // Create an empty array that we'll populate with the filenames to delete

        foreach ($finder as $file) {
            $files[] = $file->getRelativePathname(); // Add the filename to the files array
        }

        if (!empty($files)) { // Only run this bit if there are files in the directory (which there should always be!) {
            // Remove the files, and the directory (just to be safe - there should never be anything else in there)
            $filesystem->remove(array('file', $translation_directory, $file->getRelativePathname()));

            // Recreate the directory, and give the owner write permission, and everyone else read permision (0755)
            $filesystem->mkdir($translation_directory, 0755);

            // Reinitiate the language system to recreate the language files from the database
            $this->init($this->lang);
        }
    }

    /**
     * Load Translations
     *
     * Loads the translation file. If it doesnt exist, a call is made to rebuild the file.
     * @param  string $lang Language code
     * @return array  Array of phrases for the requested translation
     */
    public function loadTranslations($lang)
    {
        if (! $lang) {
            $lang = 'en';
        }

        // Set the translation cache path to load from
        $translation_file = STORAGE_DIR. DS . 'translations' . DS . $lang.'.json';

        // Check if the language cache exists
        if ($this->file->exists($translation_file)) {
            $translations = file_get_contents($translation_file); // It exists, so load it up into a variable

            return $this->phrases[$lang] = json_decode($translations, true); // Now convert it from json back to an array
        } else {
            // The language file doesnt exist, so we need to rebuild the translations file from the database
            $language = \Language::where('language_code', '=', $lang)->first(); // Load the language
            $this->language = $language;
            $phrases = $language->LanguagePhrase()->get(); // Now load the language phrases

            $phrase_array = array(); // Create an empty array - we'll use this to store the translations in a moment
            foreach ($phrases as $phrase) {
                // Add the translation to the above array
                // We'll also do a utf8 decode here on everything to ensure we have correctly formatted strings.
                // usually this would be slow but since we're caching the result it wont be a problem.
                $phrase_array[$phrase->slug] = $phrase->text;

            }
            $phrase_string = json_encode($phrase_array); // Convert the phrases to a json string

            $this->file->dumpFile($translation_file, $phrase_string); // Dump the phrases json into a translations file

            return $this->phrases[$lang] = $phrase_array; // Return the phrase array
        }
    }

    /**
     * Format Errors
     *
     * This formats the error string returned by the validation system and returns it as a list of form errors.
     *
     * @param  string $messages JSON string of form field error language codes
     * @return string           Formatted list of form errors
     */
    public function formatErrors($messages)
    {
        $message_string = $this->get('form_errors').'<ul>';

        $messages = json_decode($messages);

        if (is_array($messages) || is_object($messages)) {

            foreach ($messages as $field => $errors) {
                foreach ($errors as $error) {
                    $error = $this->get($error);
                    $error = str_replace('{field}', '<b>'.$this->get($field).'</b>', $error);
                    $message_string .= '<li>'.$error.'</li>';
                }
            }
        }

        $message_string .'</ul>';

        return $message_string;
    }
}
