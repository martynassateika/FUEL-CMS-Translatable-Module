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

        // If the item has been persisted, then the embedded list shows all
        // the translations linked to the item. Otherwise, a notification is
        // displayed (see 'notification' method).
        if (isset($values[$this->key_field])) {
            $where = array(
                'translatable_item_id' => $values[$this->key_field],
            );
            $fields['translations_embedded_list'] = array(
                'type' => 'embedded_list',
                'label' => lang('translation_item_model_translations'),
                'create_button_label' => lang('translation_item_model_add_translation'),
                'module' => array(TRANSLATABLE_FOLDER => 'translatable_item_translation_model'),
                'cols' => array('language', 'text'),
                'method_params' => array('where' => $where),
                'create_url_params' => $where,
            );
        }

        return $fields;
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