<?php
/**
 * Class to create fields for adminlists and other uses.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner
 * @package     elections
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Class to create special list fields.
 * @package shop
 */
class FieldList extends \glFusion\FieldList
{
    /**
     * Return a cached template object to avoid repetitive path lookups.
     *
     * @return  object      Template object
     */
    protected static function init()
    {
        global $_CONF;

        static $t = NULL;

        if ($t === NULL) {
            $t = new \Template(Config::get('path') . '/templates');
            $t->set_file('field', 'fieldlist.thtml');
        } else {
            $t->unset_var('output');
            $t->unset_var('attributes');
        }
        return $t;
    }


    public static function refresh($args=array()) : string
    {
        $t = self::init();
        if (isset($args['disabled'])) {
            $blk = 'field-refresh-disabled';
        } else {
            $blk = 'field-refresh';
        }
        $t->set_block('field', $blk);
        if (isset($args['url'])) {
            $t->set_var('url', $args['url']);
        }
        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output', $blk);
        return $t->finish($t->get_var('output'));
    }


    public static function buttonLink($args)
    {
        $def_args = array(
            'url' => '!#',
            'size' => '',   // mini
            'style' => 'default',  // success, danger, etc.
            'type' => '',   // submit, reset, etc.
            'class' => '',  // additional classes
        );
        $args = array_merge($def_args, $args);

        $t = self::init();
        $t->set_block('field','field-buttonlink');

        $t->set_var(array(
            'url' => $args['url'],
            'size' => $args['size'],
            'style' => $args['style'],
            'type' => $args['type'],
            'other_cls' => $args['class'],
            'text' => $args['text'],
        ) );

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-buttonlink',true);
        return $t->finish($t->get_var('output'));
    }


    public static function resultslink($args=array()) : string
    {
        $t = self::init();
        $t->set_block('field', 'field-resultslink');
        if (isset($args['url'])) {
            $t->set_var('url', $args['url']);
        }
        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output', 'field-resultslink');
        return $t->finish($t->get_var('output'));
    }

}
