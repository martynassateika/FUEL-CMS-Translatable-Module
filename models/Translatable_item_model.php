<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(FUEL_PATH . 'models/Base_module_model.php');

class Translatable_item_model extends Base_module_model
{
    public $record_class = 'Translatable_item_item'; // the name of the record class (if it can't be determined)
    public $required = array('title'); // an array of required fields. If a key => val is provided, the key is name of the field and the value is the error message to display
    public $display_unpublished_if_logged_in = FALSE;
    protected $friendly_name = 'Translatable items'; // a friendlier name of the group of objects
    protected $singular_name = 'Translatable item'; // a friendly singular name of the object

    public function __construct()
    {
        parent::__construct('translatable_item'); // table name
        $this->load->model('translatable_item_translation_model', 'translations');
        $this->load->helper('translatable');
    }

    public function list_items($limit = NULL, $offset = NULL, $col = 'id', $order = 'asc', $just_count = FALSE)
    {
        // $this->db->select('translatable_item.id, translatable_item.title, translatable_item.published....'); uncomment and  change to the fields to be displayed
        $data = parent::list_items($limit, $offset, $col, $order, $just_count);

        if (!$just_count) {
            foreach ($data as $key => $val) {
                // format with PHP instead of MySQL so that ordering will still work with MySQL
                if (!empty($val['publish_date'])) {
                    $data[$key]['publish_date'] = date_formatter($val['publish_date'], 'm/d/Y h:ia');
                }
            }
        }
        return $data;
    }

    public function form_fields($values = array(), $related = array())
    {
        $fields = parent::form_fields($values, $related);

        $fields['translations_fieldset'] = array(
            'type' => 'section',
            'tag' => 'h3',
            'value' => lang('translation_item_model_translations'),
        );

        $translatable_id = $this->_determine_key_field_value($values);
        if (isset($translatable_id)) {
            // Only show translations if the translatable item is saved
            $translation_fields = $this->setup_fields_for_translations(
                $this->find_translations($values),
                $this->fuel->config('languages')
            );
            $fields = array_merge($fields, $translation_fields);
        }

        return $fields;
    }

    private function field_name_for_translation($language_key)
    {
        return $this->get_translation_field_name_prefix() . $language_key;
    }

    private function get_translation_field_name_prefix()
    {
        return "translation_";
    }

    /**
     * Sets up fields for each translation by retrieving existing translations
     * from the database and creating empty inputs for new ones.
     *
     * This allows users to change the language set in the configuration without
     * having to worry about the state of the database.
     *
     * @param $database_translations array existing translations in the database
     * @param $supported_languages   array set of languages for translations
     * @return array combined set of translations
     */
    private function setup_fields_for_translations($database_translations, $supported_languages)
    {
        // Set up a map from language to translation text for faster lookup
        $lang_key_to_translation_map = array();
        foreach ($database_translations as $translation) {
            $lang_key_to_translation_map[$translation->language] = $translation->text;
        }

        $fields = array();
        foreach ($supported_languages as $language_key => $language) {
            $field_name = $this->field_name_for_translation($language_key);
            $fields[$field_name] = array(
                'label' => $this->get_translation_label($language_key, $language)
            );
            if (!in_array($language_key, array_keys($lang_key_to_translation_map))) {
                // Translation has not yet been persisted
                $fields[$field_name]['value'] = '';
            } else {
                // Show existing translation
                $existing_translation = $lang_key_to_translation_map[$language_key];
                $fields[$field_name]['value'] = $existing_translation;
            }
        }
        return $fields;
    }

    /**
     * @param $language_key string two-letter country code (for flag search)
     * @param $language     string language name
     * @return string label to assign to the translation field
     */
    private function get_translation_label($language_key, $language)
    {
        $flag_icon = get_flag_for_language($language_key);
        if ($flag_icon == null) {
            $flag_icon = '';
        }
        return sprintf('%s %s', $flag_icon, $language);
    }

    /**
     * @param $translatable_item_id int ID of a translatable item
     * @param $language string language associated with the translation
     * @return object|null a single translation if found, or null
     */
    public function find_translation($translatable_item_id, $language)
    {
        return $this->translations->find_one(array(
            'translatable_item_id' => $translatable_item_id,
            'language' => $language,
        ));
    }

    /**
     * @param $values array values of this translatable item
     * @return array existing translations for this translatable item, if saved,
     *         otherwise an empty array
     */
    private function find_translations($values)
    {
        $translatable_id = $this->_determine_key_field_value($values);
        if (!isset($translatable_id)) {
            return array();
        }
        return $this->translations->find_all(array(
            'translatable_item_id' => $translatable_id
        ));
    }

    /**
     * Ensures translations are deleted along with the parent translatable item
     * @param $where array condition for deleting
     */
    public function on_after_delete($where)
    {
        // Delete all translations related to this translatable item
        $this->translations->delete(array(
            'translatable_item_id' => $this->_determine_key_field_value($where)
        ));
    }

    /**
     * @param array $values
     * @return array
     */
    public function on_before_post($values = array())
    {
        $values = parent::on_before_post($values);

        if (!isset($values[$this->key_field()])) {
            // Item has not been saved before. Translation fields will not
            // have been shown so no additional processing is needed.
            return $values;
        }

        // Filter the set of all POSTed fields, retaining only the translations
        $translation_fields = array_filter($values, function ($value) {
            return $this->starts_with($value, $this->get_translation_field_name_prefix());
        }, ARRAY_FILTER_USE_KEY);

        $translatable_item_id = $values[$this->key_field()];
        $all_languages = $this->fuel->config('languages');

        // Update existing translations, and insert new ones
        foreach ($all_languages as $key => $language) {
            $field_name = $this->field_name_for_translation($key);
            if (array_key_exists($field_name, $translation_fields)) {
                $translation = $translation_fields[$field_name];
                if (!$this->find_translation($translatable_item_id, $key)) {
                    $this->translations->insert(array(
                        'translatable_item_id' => $translatable_item_id,
                        'text' => $translation,
                        'language' => $key
                    ));
                } else {
                    $this->translations->update(array(
                        'text' => $translation,
                    ), array(
                        'translatable_item_id' => $translatable_item_id,
                        'language' => $key
                    ));
                }
            }
        }
        return $values;
    }

    /**
     * @param $haystack string
     * @param $needle string
     * @return bool true if $haystack starts with $needle
     */
    private function starts_with($haystack, $needle)
    {
        $length = strlen($needle);
        $substring = substr($haystack, 0, $length);
        return $substring === $needle;
    }

    /**
     * @param array $data Translatable item's field values
     * @return bool|string FALSE if the entry has already been saved, else a
     * warning that the item must be saved before adding translations
     */
    public function notification($data = array())
    {
        if (!isset($data[$this->key_field])) {
            return lang('translation_item_model_notification_save_before_adding_translations');
        } else {
            return FALSE;
        }
    }

}

class Translatable_item_item_model extends Base_module_record
{

    /**
     * @param string $default_value value to show if translation was not found
     * @return mixed this item's translation in the current language
     */
    public function for_current_lang($default_value = '')
    {
        return $this->for_lang(detect_lang(), $default_value);
    }

    /**
     * @param $language string language associated with the translation
     * @param string $default_value value to show if translation was not found
     * @return mixed translation text if translation found, or $default_value
     */
    public function for_lang($language, $default_value = '')
    {
        $translatable_id = $this->_fields['id'];
        $translation = $this->_parent_model->find_translation(
            $translatable_id,
            $language
        );
        if ($translation) {
            return $translation->text;
        }
        return $default_value;
    }

}
