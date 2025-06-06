<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2025 Robert Nelson <robert-telnyx-sms@nelson.house>
?>
<script>
  window.addEventListener("load", (event) => {
     let form = document.getElementById("frm_telnyx_sms");
     form.addEventListener("formdata", (event) => {
        return generateFormData(event);
     });
  });
</script>
<div class="fpbx-container container-fluid">
  <div class="align-item-start">
    <a class="btn btn-default" href="?display=extensions&amp;>" >
      <i class="fa fa-plus"></i>
      <strong><?php echo sprintf(_('Add New %s Extension'),$driver['shortName'])?></strong>
    </a>
    <br/>
    <div class="display no-border">
      <div class="nav-container">
        <div class="scroller scroller-left"><i class="fa fa-chevron-left"></i></div>
        <div class="scroller scroller-right"><i class="fa fa-chevron-right"></i></div>
        <div class="wrapper">
          <ul class="nav nav-tabs list" role="tablist">
            <li role="presentation" data-name="smsnumbers" class="active">
              <a href="#smsnumbers" aria-controls="smsnumbers" role="tab" data-toggle="tab">
                SMS Numbers
              </a>
            </li>
            <li role="presentation" data-name="smsextensions" class="">
              <a href="#smsextensions" aria-controls="smsextensions" role="tab" data-toggle="tab">
                SMS Extensions
              </a>
            </li>
          </ul>
        </div>
      </div>
      <div class="tab-content display">
        <div role="tabpanel" id="smsnumbers" class="tab-pane active">
          <table id="tsmsgrid"
                 data-url="ajax.php?module=telnyx_sms&amp;command=getJSON&amp;jdata=grid"
                 data-cache="false"
                 data-toggle="table"
                 data-search="true"
                 data-pagination="true"
                 class="table table-striped">
            <thead>
            <!--Telnyx Token-->
            <div class="element-container">
              <div class="row">
                <div class="col-md-12">
                  <div class="row">
                    <div class="form-group">
                      <div class="col-md-3">
                        <label class="control-label" for="telnyx_token">
                          <?php echo _("Telnyx Token") ?>
                        </label>
                        <i class="fa fa-question-circle fpbx-help-icon" data-for="telnyx_token"></i>
                      </div>
                      <div class="col-md-9">
                        <input type="text" class="form-control" id="telnyx_token" name="telnyx_token"
                               value="<?php echo isset($telnyx_token) ? stripslashes((string)$telnyx_token) : "" ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <span id="telnyx_token-help" class="help-block fpbx-help-block">
                      <?php echo _("API Token from Telnyx to authenticate.") ?>
                  </span>
                </div>
              </div>
            </div>
            <!--END Telnyx Token-->
            <tr>
              <th data-field="phone" data-formatter="phoneFormatter">
                <?php echo _("Phone Number") ?>
              </th>
              <th class="col-md-2" data-field="link" data-formatter="actionFormatter">
                <?php echo _("Actions") ?>
              </th>
            </tr>
            </thead>
          </table>
        </div>
        <div role="tabpanel" id="smsextemsions" class="tab-pane">
          <?php echo load_view("extensions.php"); ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php } ?>




    <form class='fpbx-submit' name="frm_telnyx_sms" id="frm_telnyx_sms" method="POST" action="config.php?display=telnyx_sms">
</form>
