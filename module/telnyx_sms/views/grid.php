<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2025 Robert Nelson <robert-telnyx-sms@nelson.house>
?>

<div class="container-fluid">
  <div class="display full-border">
    <div class="row">
      <div class="col-sm-12">
        <div class="fpbx-container">
          <ul class="nav nav-tabs" role="tablist">
            <li role="presentation" data-name="smsnumbers" class="active">
              <a href="#smsnumbers" aria-controls="smsnumbers" role="tab" data-toggle="tab">
                <?php echo _("SMS Numbers"); ?>
              </a>
            </li>
            <li role="presentation" data-name="smsextensions" class="">
              <a href="#smsextensions" aria-controls="smsextensions" role="tab" data-toggle="tab">
                <?php echo _("SMS Extensions"); ?>
              </a>
            </li>
          </ul>
          <div class="tab-content display">
            <div role="tabpanel" id="smsnumbers" class="tab-pane active">
              <?php echo load_view(__DIR__.'/smsnumbers.php', array(
                  'telnyx_token'=>$telnyx_token
              ));?>
            </div>
            <div role="tabpanel" id="smsextensions" class="tab-pane">
              <?php echo load_view(__DIR__."/extensions.php", array(
                  'db'=>$db
              )); ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
