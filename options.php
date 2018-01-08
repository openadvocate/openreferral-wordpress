<?php
  $host = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
?>

<div class="wrap">
  <h1>Open Referral</h1>

  <form method="post" action="options.php" name="openref_admin_form">
    <?php settings_fields('openreferral'); ?>

    <table class="form-table">
      <tr valign="top">
        <th scope="row">Open Referral API Base URL:</th>
        <td>
          <input type="text" name="openref_api_base_url" value="<?php echo get_option('openref_api_base_url'); ?>" style="width: 75%;" />
        </td>
      </tr>
      <tr valign="top">
        <th scope="row">Open Referral API Key:</th>
        <td>
          <input type="text" name="openref_api_key" value="<?php echo get_option('openref_api_key'); ?>" style="width: 75%;" />
        </td>
      </tr>
    </table>
    <div style="border: 1px solid #aaa; padding: 1em;">
      Follow these steps to enable <a href="http://docs.openreferral.org/en/1.1/reference">Open Referral</a>
      <ul>
        <li>
          Build airtable data.<br>
          See <a href="https://airtable.com/shrorZeLSK0jAcKzt">sample data</a><br>
        </li>
        <li>
          Three tables will be exposed along with datapackage.json.<br>
          - <?= $host ?>/openreferral/v1/datapackage.json<br>
          - <?= $host ?>/openreferral/v1/organizations.csv<br>
          - <?= $host ?>/openreferral/v1/phones.csv<br>
          - <?= $host ?>/openreferral/v1/postal_addresses.csv
        </li>
        <li>
          Enter your airtable's API base URL. The URL looks like,<br>
          https://api.airtable.com/v0/appwGOAzGx8vVPKDi
        </li>
        <li>
          Allow your airtable to be shared by OpenAdvocate admin to have access to the airtable.
        </li>
      </ul>
      <p>Note that fetching airtable data can take as long as 30 seconds. Once cached, it will be quick.</p>
    </div>

    <p class="submit">
      <input type="hidden" name="formaction" value="default" />
      <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
      <input type="button" class="button" name="openref_test_connection" value="<?php _e('Test Connection') ?>" onclick="openref_submit_form(this, document.forms['openref_admin_form'])" />
      <input type="button" class="button-secondary" name="openref_purge_cache" value="<?php _e('Purge Cache') ?>" onclick="openref_submit_form(this, document.forms['openref_admin_form'])" />
    </p>
    <script>
      function openref_submit_form(element, form){ 
         form['formaction'].value = element.name;
         form.submit();
       }
    </script>
  </form>
</div>
