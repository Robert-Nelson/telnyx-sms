<script>
  window.addEventListener("load", (event) => {
    let form = document.getElementById("frm_telnyx_sms_num");
    form.addEventListener("formdata", (event) => {
      return generateNumberFormData(event);
    });
  });

  function phoneFormatter(value) {
    let html;

    if (value !== '') {
      let bareNumber = value.replaceAll(reNonDigits,"");
      let formattedNumber = bareNumber.substr(0, 3) + "-" + bareNumber.substr(3, 3) + "-" + bareNumber.substr(6, 4);
      html = '<span class="phonenumber" data-phone="'+bareNumber+'">'+formattedNumber+'</span>';
    } else {
      html = '<span class="phonenumber"><input id="newNumber"/></span>';
    }
    return html;
  }

  function actionFormatter(value) {
    let html;

    if (value[0] !== 0) {
      html = '<a onclick="return deleteRow(this, '+value[0]+')"'+' class="delAction"><i class="fa fa-minus"></i></a>&nbsp;';
    } else {
      html = '<a id="addLink" onclick="return addRow(this);" class="addAction"><i class="fa fa-plus"></i></a>&nbsp;';
    }
    return html;
  }

  function addRow(anchorElement) {
    let inputElement = document.getElementById("newNumber");
    let newPhoneNumber = inputElement.value.replaceAll(reNonDigits,"");
    let formattedNumber = newPhoneNumber.substr(0, 3) + "-" + newPhoneNumber.substr(3, 3) + "-" + newPhoneNumber.substr(6, 4);

    inputElement.value = '';

    let trElement = anchorElement.parentElement.parentElement;
    let tbodyElement = trElement.parentElement;
    let rowIndex = trElement.sectionRowIndex;
    let spanElement = document.createElement("span");
    spanElement.className = 'phonenumber newPhone';
    spanElement.dataset.phone = newPhoneNumber;
    spanElement.insertAdjacentText("AfterBegin", formattedNumber);
    let newtrElement = tbodyElement.insertRow(rowIndex);
    let newphonetdElement = newtrElement.insertCell();
    newphonetdElement.insertAdjacentElement("AfterBegin", spanElement);

    let newAnchorElement = document.createElement("a");
    newAnchorElement.setAttribute("onclick","return deleteRow(this, -1);");
    newAnchorElement.className = "delAction";
    let newIconElement = document.createElement("i");
    newIconElement.className = "fa fa-minus";
    newAnchorElement.insertAdjacentElement("AfterBegin", newIconElement);
    let mewactiontdElement = newtrElement.insertCell();
    mewactiontdElement.insertAdjacentElement("AfterBegin", newAnchorElement);
  }

  function deleteRow(anchorElement, id) {
    let trElement = anchorElement.parentElement.parentElement;

    if (id === -1) {
      trElement.remove();
    } else {
      trElement.className = "deletedRow";
      trElement.setAttribute("data-id", id);
    }
  }
</script>

<form class='fpbx-submit' name="frm_telnyx_sms_num" id="frm_telnyx_sms_num" method="POST" novalidate action="config.php?display=telnyx_sms&action=smsnum">
  <table id="tsmsgrid"
         data-url="ajax.php?module=telnyx_sms&amp;command=getJSON&amp;jdata=grid"
         data-cache="false"
         data-toggle="table"
         data-search="true"
         data-pagination="true"
         class="table table-striped">
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
      <thead>
        <th data-field="phone" data-formatter="phoneFormatter">
          <?php echo _("Phone Number") ?>
        </th>
        <th class="col-md-2" data-field="link" data-formatter="actionFormatter">
          <?php echo _("Actions") ?>
        </th>
    </thead>
  </table>
</form>
