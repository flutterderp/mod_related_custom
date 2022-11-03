<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_related_custom
 *
 * @copyright   (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

// Include the related_custom functions only once
JLoader::register('ModRelatedcustomHelper', __DIR__ . '/helper.php');

$cacheparams               = new stdClass;
$cacheparams->cachemode    = 'safeuri';
$cacheparams->class        = 'ModRelatedcustomHelper';
$cacheparams->method       = 'getList';
$cacheparams->methodparams = $params;
$cacheparams->modeparams   = array('id' => 'array', 'Itemid' => 'int');

$backfill   = false;
$list       = ModuleHelper::moduleCache($module, $params, $cacheparams);
$show_empty = (int) $params->get('show_empty_module', 0, 'int');

$params->set('running_count', count($list));

if (count($list) < $params->get('maximum', 0) && $params->get('backfill', 0) != 0)
{
	// do the backfill
	$cacheparams->class        = 'ModRelatedcustomHelper';
	$cacheparams->method       = 'getBackFill';
	$cacheparams->methodparams = $params;

	$backfill = ModuleHelper::moduleCache($module, $params, $cacheparams);
}

/* if (!count($list))
{
	return;
} */

$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_COMPAT, 'UTF-8');

if ((int) $show_empty !== 0 || !empty($list) || !empty($backfill))
{
	require ModuleHelper::getLayoutPath('mod_related_custom', $params->get('layout', 'default'));
}
