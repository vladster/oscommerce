<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2006 osCommerce

  Released under the GNU General Public License
*/

  require('../includes/classes/currencies.php');
  $osC_Currencies = new osC_Currencies();

  require('includes/classes/tax.php');
  $osC_Tax = new osC_Tax_Admin();

  $osC_Weight = new osC_Weight();

  require('../includes/classes/customer.php');
  require('../includes/classes/navigation_history.php');
  require('../includes/classes/shopping_cart.php');

  require('includes/classes/geoip.php');
  $osC_GeoIP = osC_GeoIP_Admin::load();
  if ($osC_GeoIP->isInstalled()) {
    $osC_GeoIP->activate();
  }

  $xx_mins_ago = (time() - 900);

// remove entries that have expired
  $Qdelete = $osC_Database->query('delete from :table_whos_online where time_last_click < :time_last_click');
  $Qdelete->bindTable(':table_whos_online', TABLE_WHOS_ONLINE);
  $Qdelete->bindValue(':time_last_click', $xx_mins_ago);
  $Qdelete->execute();
?>

<h1><?php echo osc_link_object(osc_href_link(FILENAME_DEFAULT, $osC_Template->getModule()), $osC_Template->getPageTitle()); ?></h1>

<?php
  if ($osC_MessageStack->size($osC_Template->getModule()) > 0) {
    echo $osC_MessageStack->output($osC_Template->getModule());
  }
?>

<div id="infoBox_wDefault" <?php if (!empty($_GET['action'])) { echo 'style="display: none;"'; } ?>>
  <table border="0" width="100%" cellspacing="0" cellpadding="2" class="dataTable">
    <thead>
      <tr>
        <th width="22">&nbsp;</th>
        <th><?php echo TABLE_HEADING_ONLINE; ?></th>
        <th><?php echo TABLE_HEADING_FULL_NAME; ?></th>
        <th><?php echo TABLE_HEADING_LAST_CLICK; ?></th>
        <th><?php echo TABLE_HEADING_LAST_PAGE_URL; ?></th>
        <th><?php echo TABLE_HEADING_SHOPPING_CART_TOTAL; ?></th>
        <th><?php echo TABLE_HEADING_ACTION; ?></th>
      </tr>
    </thead>
    <tbody>

<?php
  $Qwho = $osC_Database->query('select customer_id, full_name, ip_address, time_entry, time_last_click, session_id from :table_whos_online order by time_last_click desc');
  $Qwho->bindTable(':table_whos_online', TABLE_WHOS_ONLINE);
  $Qwho->setBatchLimit($_GET['page'], MAX_DISPLAY_SEARCH_RESULTS);
  $Qwho->execute();

  while ($Qwho->next()) {
    if (STORE_SESSIONS == 'mysql') {
      $Qsession = $osC_Database->query('select value from :table_sessions where sesskey = :sesskey');
      $Qsession->bindTable(':table_sessions', TABLE_SESSIONS);
      $Qsession->bindValue(':sesskey', $Qwho->value('session_id'));
      $Qsession->execute();

      $session_data = trim($Qsession->value('value'));
    } else {
      if ( (file_exists($osC_Session->getSavePath() . '/sess_' . $Qwho->value('session_id'))) && (filesize($osC_Session->getSavePath() . '/sess_' . $Qwho->value('session_id')) > 0) ) {
        $session_data = trim(file_get_contents($osC_Session->getSavePath() . '/sess_' . $Qwho->value('session_id')));
      }
    }

    $navigation = unserialize(osc_get_serialized_variable($session_data, 'osC_NavigationHistory_data', 'array'));
    $last_page = end($navigation);

    $currency = unserialize(osc_get_serialized_variable($session_data, 'currency', 'string'));

    $cart = unserialize(osc_get_serialized_variable($session_data, 'osC_ShoppingCart_data', 'array'));

    if (!isset($wInfo) && (!isset($_GET['info']) || (isset($_GET['info']) && ($_GET['info'] == $Qwho->value('session_id'))))) {
      $wInfo = new objectInfo(array_merge($Qwho->toArray(), array('last_page' => $last_page)));
    }
?>

      <tr onmouseover="rowOverEffect(this);" onmouseout="rowOutEffect(this);">
        <td align="center">

<?php
    if ($osC_GeoIP->isActive() && $osC_GeoIP->isValid($Qwho->value('ip_address'))) {
      echo osc_image('../images/worldflags/' . $osC_GeoIP->getCountryISOCode2($Qwho->value('ip_address')) . '.png', $osC_GeoIP->getCountryName($Qwho->value('ip_address')) . ', ' . $Qwho->value('ip_address'), 18, 12);
    } else {
      echo osc_image('images/pixel_trans.gif', $Qwho->value('ip_address'), 18, 12);
    }
?>

        </td>
        <td><?php echo gmdate('H:i:s', time() - $Qwho->value('time_entry')); ?></td>
        <td><?php echo $Qwho->value('full_name') . ' (' . $Qwho->valueInt('customer_id') . ')'; ?></td>
        <td><?php echo date('H:i:s', $Qwho->value('time_last_click')); ?></td>
        <td><?php echo $last_page['page']; ?></td>
        <td><?php echo $osC_Currencies->format($cart['total_cost'], true, $currency); ?></td>
        <td align="right">

<?php
    if (isset($wInfo) && ($Qwho->value('session_id') == $wInfo->session_id)) {
      echo osc_link_object('#', osc_icon('info.png', IMAGE_INFO), 'onclick="toggleInfoBox(\'wInfo\');"');
    } else {
      echo osc_link_object(osc_href_link_admin(FILENAME_DEFAULT, $osC_Template->getModule() . '&info=' . $Qwho->value('session_id') . '&action=wInfo'), osc_icon('info.png', IMAGE_INFO));
    }
?>

        </td>
      </tr>

<?php
  }
?>

    </tbody>
  </table>

  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <tr>
      <td><?php echo $Qwho->displayBatchLinksTotal(TEXT_DISPLAY_NUMBER_OF_WHOS_ONLINE); ?></td>
      <td align="right"><?php echo $Qwho->displayBatchLinksPullDown('page', $osC_Template->getModule()); ?></td>
    </tr>
  </table>
</div>

<?php
  if (isset($wInfo)) {
    $last_page_url = $wInfo->last_page['page'];

    if (isset($wInfo->last_page['get']['osCsid'])) {
      unset($wInfo->last_page['get']['osCsid']);
    }

    if (sizeof($wInfo->last_page['get']) > 0) {
      $last_page_url .= '?' . osc_array_to_string($wInfo->last_page['get']);
    }
?>

<div id="infoBox_wInfo" <?php if ($_GET['action'] != 'wInfo') { echo 'style="display: none;"'; } ?>>
  <div class="infoBoxHeading"><?php echo osc_icon('info.png', IMAGE_INFO) . ' ' . $wInfo->full_name; ?></div>
  <div class="infoBoxContent">
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_SESSION_ID . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo $wInfo->session_id; ?></td>
      </tr>
      <tr>
        <td class="smallText" colspan="2">&nbsp;</td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_TIME_ONLINE . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo gmdate('H:i:s', time() - $wInfo->time_entry); ?></td>
      </tr>
      <tr>
        <td class="smallText" colspan="2">&nbsp;</td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_CUSTOMER_ID . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo $wInfo->customer_id; ?></td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_CUSTOMER_NAME . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo $wInfo->full_name; ?></td>
      </tr>
      <tr>
        <td class="smallText" colspan="2">&nbsp;</td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_IP_ADDRESS . '</b>'; ?></td>
        <td class="smallText" width="60%">

<?php
    echo $wInfo->ip_address;

    if ($osC_GeoIP->isActive() && $osC_GeoIP->isValid($wInfo->ip_address)) {
      echo '<p>' . implode('<br />', $osC_GeoIP->getData($wInfo->ip_address)) . '</p>';
    }
?>

        </td>
      </tr>
      <tr>
        <td class="smallText" colspan="2">&nbsp;</td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_ENTRY_TIME . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo date('H:i:s', $wInfo->time_entry); ?></td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_LAST_CLICK . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo date('H:i:s', $wInfo->time_last_click); ?></td>
      </tr>
      <tr>
        <td class="smallText" width="40%"><?php echo '<b>' . TEXT_LAST_PAGE_URL . '</b>'; ?></td>
        <td class="smallText" width="60%"><?php echo $last_page_url; ?></td>
      </tr>

<?php
    if (!empty($cart['contents'])) {
      echo '      <tr>' . "\n" .
           '        <td class="smallText" colspan="2">&nbsp;</td>' . "\n" .
           '      </tr>' . "\n" .
           '      <tr>' . "\n" .
           '        <td class="smallText" width="40%" valign="top"><b>' . TEXT_SHOPPING_CART_PRODUCTS . '</b></td>' . "\n" .
           '        <td class="smallText" width="60%"><table border="0" cellspacing="0" cellpadding="2">' . "\n";

      foreach ($cart['contents'] as $product) {
        echo '          <tr>' . "\n" .
             '            <td class="smallText" align="right">' . $product['quantity'] . ' x</td>' . "\n" .
             '            <td class="smallText">' . $product['name'] . '</td>' . "\n" .
             '          </tr>' . "\n";
      }

      echo '        </table></td>' . "\n" .
           '      </tr>' . "\n" .
           '      <tr>' . "\n" .
           '        <td class="smallText" width="40%"><b>' . TEXT_SHOPPING_CART_TOTAL . '</b></td>' . "\n" .
           '        <td class="smallText" width="60%">' . $osC_Currencies->format($cart['total_cost'], true, $currency) . '</td>' . "\n" .
           '      </tr>' . "\n";
    }
?>

    </table>

    <p align="center"><?php echo '<input type="button" value="' . IMAGE_BACK . '" onclick="toggleInfoBox(\'wDefault\');" class="operationButton">'; ?></p>
  </div>
</div>

<?php
  }

  if ($osC_GeoIP->isActive()) {
    $osC_GeoIP->deactivate();
  }
?>
