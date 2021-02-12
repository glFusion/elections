<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Elections;


/**
 * Class to provide admin and user-facing menus.
 * @package elections
 */
class Menu
{
    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF;
        USES_lib_admin();

        $retval = '';
        $menu_arr = array (
            array(
                'url' => Config::get('admin_url') . '/index.php',
                'text' => MO::_('List All'),
                'active'=> $view == 'listall' ? true : false,
            ),
            array(
                'url' => Config::get('admin_url') . '/index.php?edit=x',
                'text' => MO::_('Create'),
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => MO::_('Admin Home'),
            ),
        );

        // Add javascript used on admin pages
        $T = new \Template(__DIR__ . '/../templates/admin');
        $T->set_file('admin_js', 'js.thtml');
        $T->set_var(array(
            'pi_name' => Config::PI_NAME,
            'ajax_url' => Config::get('admin_url') . '/ajax.php',
        ) );
        $T->parse('output', 'admin_js');
        $retval .= $T->finish($T->get_var('output'));

        $retval .= COM_startBlock(
            MO::_('Elections Administration') . ' ver. ' . Config::get('pi_version'),
            '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $retval .= ADMIN_createMenu(
            $menu_arr,
            '',
            plugin_geticon_elections()
        );

        return $retval;
    }


    /**
     * Display the site header, with or without blocks according to configuration.
     *
     * @param   string  $title  Title to put in header
     * @param   string  $meta   Optional header code
     * @return  string          HTML for site header, from COM_siteHeader()
     */
    public static function siteHeader($title='', $meta='')
    {
        $retval = '';

        switch (Config::get('displayblocks')) {
        case 0 : // left only
        case 2 :
            $retval .= COM_siteHeader('menu',$title,$meta);
            break;
        case 1 : // right only
        case 3 :
            $retval .= COM_siteHeader('none',$title,$meta);
            break;
        default :
            $retval .= COM_siteHeader('menu',$title,$meta);
            break;
        }
        return $retval;
    }


    /**
     * Display the site footer, with or without blocks as configured.
     *
     * @return  string      HTML for site footer, from COM_siteFooter()
     */
    public static function siteFooter()
    {
        global $_CONF;

        $retval = '';
        switch (Config::get('displayblocks')) {
        case 0 : // left only
        case 3 : // none
            $retval .= COM_siteFooter();
            break;
        case 1 : // right only
        case 2 : // left and right
            $retval .= COM_siteFooter( true );
            break;
        default :
            $retval .= COM_siteFooter();
            break;
        }
        return $retval;
    }

}

?>


