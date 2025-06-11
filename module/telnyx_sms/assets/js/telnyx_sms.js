const reNonDigits = /[^0-9]+/g;

$(document).on('click', 'a[data-toggle="tab"]', function (e) {
    var clicked = $(this).attr('href');
    switch (clicked) {
        case '#smsnumbers':
            $('#smsextensions').removeClass('active');
            $("li[data-name='smsextensions']").removeClass('active');
            $("li[data-name='smsnumbers']").addClass('active');
            $('#smsnumbers').addClass('active');
            break;
        case '#smsextensions':
            $('#smsnumbers').removeClass('active');
            $("li[data-name='smsnumbers']").removeClass('active');
            $("li[data-name='smsextensions']").addClass('active');
            $('#smsextensions').addClass('active');
            break;
    }
});

function generateNumberFormData(event) {
    var formData = event.formData;

    var allExtensionData = new Map();

    // Display the keys
    for (const key of formData.keys()) {
        if (key !== "telnyx_token") {
            formData.delete(key);
        }
    }

    var deletedRows = document.getElementsByClassName("deletedRow");

    for (const row of deletedRows) {
        formData.append("deleteNumbers[]", row.getAttribute("data-id"));
    }

    var addedNumbers = document.getElementsByClassName("newPhone");

    for (const phoneNumber of addedNumbers) {
        formData.append("addNumbers[]", phoneNumber.dataset.phone);
    }

    formData.append("Submit", "Submit");
}

function generateExtensionFormData(event) {
    var formData = event.formData;

    var allExtensionData = new Map();

    // Display the keys
    for (const key of formData.keys()) {
        formData.delete(key);
    }

    for (const extNumber of extens) {
        var extensionData = new Map();

        if (extcid.has(extNumber)) {
            extensionData.set("sendCid", extcid.get(extNumber));
        }

        if (extnumbers.has(extNumber)) {
            var phoneList = extnumbers.get(extNumber);
            if (phoneList.length() > 0) {
                extensionData.set("receiveNumbers", phoneList);
            }
        }

        if (extensionData.size > 0) {
            allExtensionData.set(extNumber, extensionData);
        }
    }

    formData.append("extData", JSON.stringify(allExtensionData));

    formData.append("Submit", "Submit");
}
