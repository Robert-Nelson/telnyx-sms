function phoneFormatter(value) {
    var html;

    if (value !== '') {
        html = '<span class="phonenumber">'+value+'</span>';
    } else {
        html = '<span class="phonenumber"><input id="newNumber"/></span>';
    }
    return html;

}

function actionFormatter(value) {
    var html;

    if (value[0] !== 0) {
        html = '<a onclick="return deleteRow(this, '+value[0]+')"'+' class="delAction"><i class="fa fa-minus"></i></a>&nbsp;';
    } else {
        html = '<a id="addLink" onclick="return addRow(this);" class="addAction"><i class="fa fa-plus"></i></a>&nbsp;';
    }
    return html;
}

function addRow(anchorElement) {
    var inputElement = document.getElementById("newNumber");
    var newPhoneNUmber = inputElement.value;
    inputElement.value = '';

    var trElement = anchorElement.parentElement.parentElement;
    var tbodyElement = trElement.parentElement;
    var rowIndex = trElement.sectionRowIndex;

    var spanElement = document.createElement("span");
    spanElement.className = 'phonenumber newPhone';
    spanElement.insertAdjacentText("AfterBegin", newPhoneNUmber);
    var newtrElement = tbodyElement.insertRow(rowIndex);
    var newphonetdElement = newtrElement.insertCell();
    newphonetdElement.insertAdjacentElement("AfterBegin", spanElement);

    var newAnchorElement = document.createElement("a");
    newAnchorElement.setAttribute("onclick","return deleteRow(this, -1);");
    newAnchorElement.className = "delAction";
    var newIconElement = document.createElement("i");
    newIconElement.className = "fa fa-minus";
    newAnchorElement.insertAdjacentElement("AfterBegin", newIconElement);
    var mewactiontdElement = newtrElement.insertCell();
    mewactiontdElement.insertAdjacentElement("AfterBegin", newAnchorElement);
}


function deleteRow(anchorElement, id) {
    var trElement = anchorElement.parentElement.parentElement;

    if (id === -1) {
        trElement.remove();
    } else {
        trElement.className = "deletedRow";
        trElement.setAttribute("data-id", id);
    }
}

function generateFormData(event) {
    var formData = event.formData;

    // Display the keys
    for (const key of formData.keys()) {
        if (key !== "telnyx_token") {
            formData.delete(key);
        }
    }

    var deletedRows = document.getElementsByClassName("deletedRow");

    for (const row of deletedRows) {
        formData.append("delete[]", row.getAttribute("data-id"));
    }

    var addedNumbers = document.getElementsByClassName("newPhone");

    for (const phoneNumber of addedNumbers) {
        formData.append("add[]", phoneNumber.innerText);
    }

    formData.append("Submit", "Submit");
}
