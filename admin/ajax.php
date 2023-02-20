<?php
/**
 * Common admistrative AJAX functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     elections
 * @version     v0.1.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include required glFusion common functions */
require_once '../../../lib-common.php';
use Elections\MO;

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!plugin_ismoderator_elections()) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the shop admin ajax function.");
    $retval = array(
        'status' => false,
        'statusMessage' => MO::_('Access Denied'),
    );
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    //A date in the past
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    echo json_encode($retval);
    exit;
}
use Elections\Election;
use Elections\Models\Request;

$Request = Request::getInstance();
$action = $Request->getString('action');

$title = NULL;      // title attribute to be set
switch ($action) {
case 'toggle':
    COM_errorLog(var_export($Request,true));
    $type = $Request->getString('type');
    $id = $Request->getInt('id');
    $oldval = $Request->getInt('oldval');
    $component = $Request->getString('component');
    switch ($component) {
    case 'election':
        switch ($type) {
        case 'status':
            $newval = Election::toggleEnabled($oldval, $id);
            break;
         default:
            exit;
        }
        break;
    default:
        exit;
    }

    // Common output for all toggle functions.
    $retval = array(
        'id'    => $id,
        'type'  => $type,
        'component' => $component,
        'newval'    => $newval,
        'statusMessage' => $newval != $oldval ?
            MO::_('Item(s) have been updated.') : MO::_('Item(s) are unchanged.'),
        'title' => $title,
    );
}

// Return the $retval array as a JSON string
header('Content-Type: application/json');
header("Cache-Control: no-cache, must-revalidate");
//A date in the past
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
echo json_encode($retval);
exit;
