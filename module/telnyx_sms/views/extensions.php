<script>

<?php
//include_once "/etc/freepbx.conf";

global $astman;

// Extensions
$results = $astman->command("database query \"select DISTINCT substr(key, 10, 3) AS ext FROM astdb WHERE key LIKE '/AMPUSER/%'\"");
$results = explode("\n",preg_replace("/(\n+)/","\n",$results["data"]));
$extens = array_values(preg_filter('/ext.*[^0-9]([0-9]+)/', '$1', $results));

// Numbers
$stmt = $db->prepare("select * from smsnumbers;");
$stmt->execute();
$numbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$smsnumbers = array();
$smsnumberkeys = array();

foreach ($numbers as $number) {
	$smsnumbers[$number["ID"]] = $number["Phone"];
	$smsnumberkeys[$number["Phone"]] = $number["ID"];
}

// Ext CID
$stmt = $db->prepare("select * from smscid");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$extcid = array();
foreach ($rows as $row) {
	$extcid[$row["Exten"]] = $row;
}

// ExtNumbers
$stmt = $db->prepare("select * from smsextens;");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$extnumbers = array();
foreach ($rows as $row) {
	$ext = $row["Exten"];
	if (!array_key_exists($ext, $extnumbers)) {
		$extnumbers[$ext] = array();
	}
	$extnumbers[$ext][$smsnumbers[$row["Phone_ID"]]] = $row;
}

echo "var extens = ".json_encode($extens, JSON_PRETTY_PRINT).";\n";
echo "var smsnumbers = ".json_encode($smsnumbers, JSON_PRETTY_PRINT).";\n";
echo "var smsnumberkeys = ".json_encode($smsnumberkeys, JSON_PRETTY_PRINT).";\n";
echo "var extcid = ".json_encode($extcid, JSON_PRETTY_PRINT).";\n";
echo "var extnumbers = ".json_encode($extnumbers, JSON_PRETTY_PRINT).";\n";
?>
function selectExtension(inputElement) {
  var ext = inputElement.value;

  var smscidElem =  document.getElementById("smscid");
  smscidElem.value = smsnumbers[extcid[ext]["Phone_ID"]];

  for (const phonekey in smsnumbers) {
	  var phone = smsnumbers[phonekey];
	  var elem = document.getElementById("check_"+phone);

	  elem.checked = ext in extnumbers && phone in extnumbers[ext];
  }
}

function selectCID(inputElement) {
	var ext = document.getElementById("extension").value;

	if (inputElement.value !== "") {
		extcid[ext] = {
			"Exten": ext,
			"Phone_ID": smsnumberkeys[inputElement.value]
		};
	} else {
		if (ext in extcid) {
			delete extcid[ext];
		}
	}
}

function selectReceivedNumber(inputElement) {
	var ext = document.getElementById("extension").value;
	var phone = inputElement.dataset["phone"]

	if (inputElement.checked) {
		extnumbers[ext][phone] = {
			"Phone_ID": smsnumberkeys[phone],
			"Exten": ext
		};
	} else {
		delete extnumbers[ext][phone];
	}
}
</script>
<?php
$current_ext = $extens[0];
?>
<center><h1>Extension SMS Settings</h1></center>

<table align="center">
  <tbody?
    <tr>
      <td align="left">
        <h2>Show settings for</h2>
        <label>Extension
	      <select name="extension" id="extension"  oninput="selectExtension(this)">
<?php
foreach ($extens as $ext) {
	echo "            <option value=\"$ext\"";
if ($current_ext === $ext) {
	echo " selected=\"selected\"";
}
        echo ">$ext</option>\n";
}
?>
          </select>
        </label>
      </td>
    </tr>
    <tr>
      <td align="left">
        <h2>Sent Caller ID</h2>
        <label>SMS Number
          <select name="smscid" id="smscid" oninput="selectCID(this)">
            <option value=""<?php
$cidphone = NULL;
if (array_key_exists($current_ext,$extcid)) {
  $cidkey = $extcid[$current_ext]["Phone_ID"];
  $cidphone = $smsnumbers[$cidkey];
}
if (is_null($cidphone)) {
	echo " selected=\"selected\"";
}
echo ">Send Disabled</option>\n";
foreach ($smsnumbers as $number) {
echo "            <option id=cid$number value=\"$number\"";
if ($cidphone === $number) {
	echo " selected=\"selected\"";
}
		echo ">(".substr($number,0,3).") ".substr($number,3, 3)."-".substr($number,6,4)."</option>\n";
}
?>
          </select>
        </label>
      </td>
    </tr>
    <tr>
      <td align="left">
        <h2>Receive Messages sent to</h2>
<?php
foreach ($smsnumbers as $number) {
	echo "        <label>(".substr($number,0,3).") ".substr($number,3, 3)."-".substr($number,6,4)."\n          <input type=\"checkbox\" id=check_$number name=check_$number data-phone=\"$number\"";
	if (array_key_exists($current_ext, $extnumbers) && array_key_exists($number, $extnumbers[$current_ext])) {
		echo " checked=\"checked\"";
	}
  echo " oninput=\"selectReceivedNumber(this)\" />\n";
  echo "        </label><br/>\n";
}
?>
      </td>
    </tr>
  </tbody>
</table>
