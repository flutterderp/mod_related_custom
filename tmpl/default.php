<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_related_items
 *
 * @copyright   (C) 2013 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
?>
<div class="tagssimilar<?php echo $moduleclass_sfx; ?>">
<?php if (empty($list) && empty($backfill)) : ?>
	<span><?php echo Text::_('MOD_RELATED_CUSTOM_NO_MATCHING_TAGS'); ?></span>
<?php else : ?>
	<ul>
	<?php foreach ($list as $i => $item) : ?>
		<li>
			<?php if (($item->type_alias === 'com_users.category') || ($item->type_alias === 'com_banners.category')) : ?>
				<?php if (!empty($item->core_title)) : ?>
					<?php echo htmlspecialchars($item->core_title, ENT_COMPAT, 'UTF-8'); ?>
				<?php endif; ?>
			<?php else : ?>
				<?php
				$images      = new Registry($item->core_images);
				$images      = $images->toArray();
				$image_intro = isset($images['image_intro']) ? $images['image_intro'] : $item->core_images;
				?>
				<a href="<?php echo Route::_($item->link); ?>">
					<?php if($image_intro && file_exists(JPATH_BASE . '/' . $image_intro)) : ?>
						<img src="<?php echo $image_intro; ?>" alt="<?php echo $item->core_alias; ?>" loading="lazy">
					<?php else : ?>
						<img src="https://via.placeholder.com/510x340/ccc/ccc" alt="<?php echo $item->core_alias; ?>" loading="lazy">
					<?php endif; ?>

					<?php if (!empty($item->core_title)) : ?>
						<?php echo htmlspecialchars($item->core_title, ENT_COMPAT, 'UTF-8'); ?>
					<?php endif; ?>
				</a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>

	<?php if($backfill) : ?>
		<?php foreach ($backfill as $i => $item) : ?>
			<li class="backfill-item">
				<?php if (($item->type_alias === 'com_users.category') || ($item->type_alias === 'com_banners.category')) : ?>
					<?php if (!empty($item->core_title)) : ?>
						<?php echo htmlspecialchars($item->core_title, ENT_COMPAT, 'UTF-8'); ?>
					<?php endif; ?>
				<?php else : ?>
					<?php
					$images      = new Registry($item->core_images);
					$images      = $images->toArray();
					$image_intro = isset($images['image_intro']) ? $images['image_intro'] : $item->core_images;
					$image_intro = HTMLHelper::cleanImageUrl($image_intro);
					?>
					<a href="<?php echo Route::_($item->link); ?>">
						<?php if($image_intro->url && file_exists(JPATH_BASE . '/' . $image_intro->url)) : ?>
							<img src="<?php echo $image_intro->url; ?>" alt="<?php echo $item->core_alias; ?>" loading="lazy">
						<?php else : ?>
							<img src="https://via.placeholder.com/510x340/ccc/ccc" alt="<?php echo $item->core_alias; ?>" loading="lazy">
						<?php endif; ?>

						<?php if (!empty($item->core_title)) : ?>
							<?php echo htmlspecialchars($item->core_title, ENT_COMPAT, 'UTF-8'); ?>
						<?php endif; ?>
					</a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	<?php endif; ?>
	</ul>
<?php endif; ?>
</div>
