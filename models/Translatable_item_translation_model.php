<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(FUEL_PATH . 'models/Base_module_model.php');

class Translatable_item_translation_model extends Base_module_model
{
    public $hidden_fields = array('translatable_item_id');
    public $record_class = 'Translatable_item_translation_item'; // the name of the record class (if it can't be determined)
    public $required = array('translatable_item_id', 'language'); // an array of required fields. If a key => val is provided, the key is name of the field and the value is the error message to display
    public $foreign_keys = array(
        'translatable_item_id' => array(TRANSLATABLE_FOLDER => 'translatable_item_model')
    ); // map foreign keys to table models
    protected $friendly_name = 'Translations'; // a friendlier name of the group of objects
    protected $singular_name = 'Translation'; // a friendly singular name of the object

    public function __construct()
    {
        parent::__construct('translatable_item_translation'); // table name
    }

    public function list_items($limit = NULL, $offset = NULL, $col = 'id', $order = 'asc', $just_count = FALSE)
    {
        $this->db->select('translatable_item_translation.id, translatable_item_translation.translatable_item_id, translatable_item_translation.language, translatable_item_translation.text');
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

    /**
     * Add FUEL specific changes to the form_fields method
     *
     * @access  public
     * @param   $values array Values of the form fields (optional)
     * @param   $related array An array of related fields. This has been deprecated in favor of using has_many and belongs to relationships (deprecated)
     * @return  array An array to be used with the Form_builder class
     */
    public function form_fields($values = array(), $related = array())
    {
        $form_fields = parent::form_fields($values, $related);
        if (empty($values['translatable_item_id'])) {
            return array(); // creation via translatable module
        }
        return $form_fields;
    }

    /**
     * Adds a filter that allows filtering translations by translatable item
     *
     * @param array $values
     * @return array
     */
    public function filters($values = array())
    {
        $filters = parent::filters($values);

        $CI =& get_instance();
        $CI->load->model('translatable_item_model');
        $translatable_data = $this->translatable_item_model->find_all();
        $translatables = array();
        foreach ($translatable_data as $translatable) {
            $translatables[$translatable->id] = $translatable->title;
        }

        $filters['translatable_item_id'] = array(
            'label' => 'Translatables:',
            'type' => 'select',
            'options' => $translatables,
            'first_option' => lang('label_select_one'),
        );
        return $filters;
    }

    /**
     * Placeholder hook - right before validation of data
     *
     * @access public
     * @param $values array values to be saved
     * @return array
     */
    public function on_before_validate($values)
    {
        $values = parent::on_before_validate($values);

        // If the translation wasn't yet persisted, check if there already
        // exists one for the same language
        if (empty($values['id'])) {
            $existing_translation = $this->find_one(array(
                'language' => $values['language'],
                'translatable_item_id' => $values['translatable_item_id'],
            ));
            if ($existing_translation) {
                $this->add_error(lang('translatable_item_translation_model_exists_for_language'));
            }
        }
        return $values;
    }

    /**
     * @param array $data translatable item translation's field values
     * @return bool|string a warning that translations cannot be added on their
     * own, and that they must be created via a translatable, if the user
     * accessed the 'Create' page
     */
    public function notification($data = array())
    {
        if (!isset($data['translatable_item_id'])) {
            return lang('translatable_item_translation_model_create_via_translatable');
        } else {
            return FALSE;
        }
    }

}

class Translatable_item_translation_item_model extends Base_module_record
{
}