<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_related_custom
 *
 * @copyright   (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
// use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Joomla\String\Inflector;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

JLoader::register('TagsHelperRoute', JPATH_BASE . '/components/com_tags/helpers/route.php');

/**
 * Helper for mod_related_custom
 *
 * @since  3.1
 */
abstract class ModRelatedcustomHelper
{
	/**
	 * Get a list of tags
	 *
	 * @param   Registry  &$params  Module parameters
	 *
	 * @return  array
	 */
	public static function getList(&$params)
	{
		// Implode is needed because the array can contain a string with a coma separated list of ids
		$typesarray = $params->get('types');
		$typesarray = implode(',', $typesarray);

		// Sanitise
		$typesarray = explode(',', $typesarray);
		$typesarray = ArrayHelper::toInteger($typesarray);

		$app        = Factory::getApplication();
		$option     = $app->input->get('option');
		$view       = $app->input->get('view');

		// For now assume com_tags and com_users do not have tags.
		// This module does not apply to list views in general at this point.
		if ($option === 'com_tags' || $view === 'category' || $option === 'com_users')
		{
			return array();
		}

		$db         = Factory::getDbo();
		$user       = Factory::getUser();
		$groups     = implode(',', $user->getAuthorisedViewLevels());
		$matchtype  = $params->get('matchtype', 'all');
		$maximum    = $params->get('maximum', 5);
		$ordering   = $params->get('ordering', 'count');
		$tagsHelper = new TagsHelper;
		$prefix     = $option . '.' . $view;
		$id         = $app->input->getInt('id');
		$now        = Factory::getDate()->toSql();
		$nullDate   = $db->getNullDate();

		$tagsToMatch = $tagsHelper->getTagIds($id, $prefix);

		if (!$tagsToMatch || $tagsToMatch === null)
		{
			return array();
		}

		$tagCount = substr_count($tagsToMatch, ',') + 1;

		$query = $db->getQuery(true)
			->select(
				array(
					$db->quoteName('m.core_content_id'),
					$db->quoteName('m.content_item_id'),
					$db->quoteName('m.type_alias'),
					'COUNT( ' . $db->quoteName('tag_id') . ') AS ' . $db->quoteName('count'),
					$db->quoteName('ct.router'),
					$db->quoteName('cc.core_title'),
					$db->quoteName('cc.core_alias'),
					$db->quoteName('cc.core_catid'),
					$db->quoteName('cc.core_images'),
					$db->quoteName('cc.core_language'),
					$db->quoteName('cc.core_params'),
				)
			);

		$query->from($db->quoteName('#__contentitem_tag_map', 'm'));

		$query->join('INNER', $db->quoteName('#__tags', 't') . ' ON m.tag_id = t.id')
			->join('INNER', $db->quoteName('#__ucm_content', 'cc') . ' ON m.core_content_id = cc.core_content_id')
			->join('INNER', $db->quoteName('#__content_types', 'ct') . ' ON m.type_alias = ct.type_alias');

		$query->where($db->quoteName('m.tag_id') . ' IN (' . $tagsToMatch . ')');
		$query->where('t.access IN (' . $groups . ')');
		$query->where('(cc.core_access IN (' . $groups . ') OR cc.core_access = 0)');

		// Don't show current item
		$query->where('(' . $db->quoteName('m.content_item_id') . ' <> ' . $id
			. ' OR ' . $db->quoteName('m.type_alias') . ' <> ' . $db->quote($prefix) . ')'
		);

		// Only return published tags
		$query->where($db->quoteName('cc.core_state') . ' = 1 ')
			->where('(' . $db->quoteName('cc.core_publish_up') . '=' . $db->quote($nullDate) . ' OR '
				. 'ISNULL(' . $db->quoteName('cc.core_publish_up') . ')' . ' OR '
				. $db->quoteName('cc.core_publish_up') . '<=' . $db->quote($now) . ')'
			)
			->where('(' . $db->quoteName('cc.core_publish_down') . '=' . $db->quote($nullDate) . ' OR '
				. 'ISNULL(' . $db->quoteName('cc.core_publish_down') . ')' . ' OR '
				. $db->quoteName('cc.core_publish_down') . '>=' . $db->quote($now) . ')'
			);

		// Optionally filter on language
		$language = ComponentHelper::getParams('com_tags')->get('tag_list_language_filter', 'all');

		if ($language !== 'all')
		{
			if ($language === 'current_language')
			{
				$language = ContentHelper::getCurrentLanguage();
			}

			$query->where($db->quoteName('cc.core_language') . ' IN (' . $db->quote($language) . ', ' . $db->quote('*') . ')');
		}

		if (count($typesarray) > 0)
		{
			$query->where($db->quoteName('m.type_id') . ' IN (' . implode(',', $typesarray). ')');
		}

		$query->group(
			$db->quoteName(
				array('m.core_content_id', 'm.content_item_id', 'm.type_alias', 'ct.router', 'cc.core_title',
				'cc.core_alias', 'cc.core_catid', 'cc.core_language', 'cc.core_params')
			)
		);

		if ($matchtype === 'all' && $tagCount > 0)
		{
			$query->having('COUNT( ' . $db->quoteName('tag_id') . ')  = ' . $tagCount);
		}
		elseif ($matchtype === 'half' && $tagCount > 0)
		{
			$tagCountHalf = ceil($tagCount / 2);
			$query->having('COUNT( ' . $db->quoteName('tag_id') . ')  >= ' . $tagCountHalf);
		}

		if ($ordering === 'latest')
		{
			$query->order($db->quoteName('cc.core_publish_up') . ' DESC');
		}

		if ($ordering === 'count' || $ordering === 'countrandom')
		{
			$query->order($db->quoteName('count') . ' DESC');
		}

		if ($ordering === 'random' || $ordering === 'countrandom')
		{
			$query->order($query->Rand());
		}

		$db->setQuery($query, 0, $maximum);

		try
		{
			$results = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$results = array();
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
		}

		foreach ($results as $result)
		{
			$result->link = TagsHelperRoute::getItemRoute(
				$result->content_item_id,
				$result->core_alias,
				$result->core_catid,
				$result->core_language,
				$result->type_alias,
				$result->router
			);

			$result->core_params = new Registry($result->core_params);
		}

		return $results;
	}

	/**
	 * Get a list of tags
	 *
	 * @param   Registry  &$params  Module parameters
	 *
	 * @return  array
	 */
	public static function getBackFill(&$params)
	{
		// Implode is needed because the array can contain a string with a coma separated list of ids
		$app          = Factory::getApplication();
		$backfilltype = (int) $params->get('backfill', 0);
		$option       = $app->input->get('option');
		$view         = $app->input->get('view');

		// For now assume com_tags and com_users do not have tags.
		// This module does not apply to list views in general at this point.
		if (($option === 'com_tags' || $view === 'category' || $option === 'com_users') && $backfilltype == 0)
		{
			return array();
		}

		$db            = Factory::getDbo();
		$user          = Factory::getUser();
		$groups        = implode(',', $user->getAuthorisedViewLevels());
		$matchtype     = $params->get('matchtype', 'all');
		$maximum       = $params->get('maximum', 5);
		$ordering      = $params->get('ordering', 'count');
		$running_count = $params->get('running_count', 0);
		$backfill_max  = $maximum - $running_count;
		$tagsHelper    = new TagsHelper;
		$contentType   = $tagsHelper->getTypes('objectList', array($backfilltype), false);
		$contentType   = $contentType[0];
		$prefix        = $option . '.' . $view;
		$id            = $app->input->getInt('id');
		$now           = Factory::getDate()->toSql();
		$nullDate      = $db->getNullDate();

		$contentType->table          = new Registry($contentType->table);
		$contentType->table          = $contentType->table->toObject();
		$contentType->field_mappings = new Registry($contentType->field_mappings);
		$contentType->field_mappings = $contentType->field_mappings->toObject();

		$table  = $contentType->table;
		$fields = $contentType->field_mappings;

		$query = $db->getQuery(true)
			->select(
				array(
					'0 AS ' . $db->quoteName('core_content_id'),
					$db->quoteName('a.' . $fields->common->core_content_item_id, 'content_item_id'),
					$db->quote($contentType->type_alias) . ' AS ' . $db->quoteName('type_alias'),
					'0 AS ' . $db->quoteName('count'),
					$db->quote($contentType->router) . ' AS ' . $db->quoteName('router'),
					$db->quoteName('a.' . $fields->common->core_title, 'core_title'),
					$db->quoteName('a.' . $fields->common->core_alias, 'core_alias'),
					$db->quoteName('a.' . $fields->common->core_catid, 'core_catid'),
					$db->quoteName('a.' . $fields->common->core_images, 'core_images'),
					$db->quoteName('a.' . $fields->common->core_language, 'core_language'),
					$db->quoteName('a.' . $fields->common->core_params, 'core_params'),
				)
			);

		$query->from($db->quoteName($table->special->dbtable, 'a'));

		$query->where('(' . $db->quoteName('a.' . $fields->common->core_access) . ' IN (' . $groups . ') OR '
			. $db->quoteName('a.' . $fields->common->core_access) . ' = 0)');

		// Don't show current item
		if ((int) $id > 0)
		{
			$query->where($db->quoteName('a.' . $fields->common->core_content_item_id) . ' <> ' . $id);
		}

		// Only return published tags
		$query->where($db->quoteName('a.' . $fields->common->core_state) . ' = 1 ')
			->where('(' . $db->quoteName('a.' . $fields->common->core_publish_up) . '=' . $db->quote($nullDate) . ' OR '
				. 'ISNULL(' . $db->quoteName('a.' . $fields->common->core_publish_up) . ')' . ' OR '
				. $db->quoteName('a.' . $fields->common->core_publish_up) . '<=' . $db->quote($now) . ')'
			)
			->where('(' . $db->quoteName('a.' . $fields->common->core_publish_down) . '=' . $db->quote($nullDate) . ' OR '
				. 'ISNULL(' . $db->quoteName('a.' . $fields->common->core_publish_down) . ')' . ' OR '
				. $db->quoteName('a.' . $fields->common->core_publish_down) . '>=' . $db->quote($now) . ')'
			);

		// Optionally filter on language
		$language = ComponentHelper::getParams('com_tags')->get('tag_list_language_filter', 'all');

		if ($language !== 'all')
		{
			if ($language === 'current_language')
			{
				$language = ContentHelper::getCurrentLanguage();
			}

			$query->where($db->quoteName('a.' . $fields->common->core_language) . ' IN (' . $db->quote($language) . ', ' . $db->quote('*') . ')');
		}

		$query->order($db->quoteName('a.' . $fields->common->core_publish_up) . ' DESC');

		$db->setQuery($query, 0, $backfill_max);

		try
		{
			$results = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$results = array();
			Factory::getApplication()->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
		}

		foreach ($results as $result)
		{
			$result->link = TagsHelperRoute::getItemRoute(
				$result->content_item_id,
				$result->core_alias,
				$result->core_catid,
				$result->core_language,
				$result->type_alias,
				$result->router
			);

			$result->core_params = new Registry($result->core_params);
		}

		return $results;
	}
}
