<?php

/*
 * Print receipt for payment made via Authorize.Net gateway
 *
 * @subpackage enrol/authorize
 * @copyright  2011 Olumuyiwa Taiwo
 * @author     Olumuyiwa Taiwo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
include '../../lib/pdflib.php';
set_time_limit(0);
require_login();

$orderid = required_param('order', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if (!$order = $DB->get_record('enrol_authorize', array('id' => $orderid))) {
    print_error('orderidnotfound', '', "$CFG->wwwroot/enrol/authorize/index.php", $orderid);
}

if (!$course = $DB->get_record('course', array('id' => $order->courseid))) {
    print_error('invalidcourseid', '', "$CFG->wwwroot/enrol/authorize/index.php");
}

if (!$user = $DB->get_record('user', array('id' => $order->userid))) {
    print_error('nousers', '', "$CFG->wwwroot/enrol/authorize/index.php");
}

$context = get_context_instance(CONTEXT_COURSE, $course->id);
if ($USER->id != $order->userid) { // Current user viewing someone else's order
    require_capability('enrol/authorize:managepayments', $context);
}

$PAGE->set_url('/enrol/authorize/receipt.php', array('id' => $order->id, 'action' => 'receipt'));
$PAGE->set_context($context);

$pdf = new TCPDF();

$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('Authurize.Net');
$pdf->SetTextColor(0, 0, 120);
$pdf->SetTitle('Payment Received');
$pdf->setPrintHeader(false);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetMargins(20, 10, 20);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

$pdf->SetFontSize(12);
$pdf->writeHTML("<strong>PAYMENT RECEIPT</strong> <br/>Receipt No.: $order->receipt (Order No.: $order->id)<br/>", true, false, false, false, 'R');
$addresshtml = get_config('enrol_authorize', 'receipt_addresshtml') .
        '<p/>' . date('M d, Y', $order->timecreated) .
        "<p/><hr/>";
$pdf->SetFontSize(12);
if (!empty($addresshtml)) {
    $pdf->writeHTML($addresshtml, true, false, false, false, 'R');
}
$plugin = enrol_get_plugin('authorize');
$cc = $DB->get_record('enrol_authorize_avs', array('orderid' => $order->id));

if ($plugin->get_config('an_avs') && $cc) {
    $userhtml = "<i>$cc->ccfirstname $cc->cclastname<br/>" .
            utf8_decode("$cc->ccaddress") . "<br/>" .
            utf8_decode("$cc->cccity") . "<br/>" .
            utf8_decode("$cc->ccstate") . "<br/>" .
            utf8_decode("$cc->cccountry") . "<p/></i>";
} else {
    $userhtml = "<i>
    $user->firstname $user->lastname<br/>" .
            utf8_decode("$user->address") . "<br/>" .
            utf8_decode("$user->city") . "<br/>" .
            utf8_decode("$user->country") . "<p/></i>";
}
$pdf->writeHTML($userhtml, true, false, false, false, 'L');

$pdf->writeHTML(utf8_decode("Dear $user->firstname $user->lastname,<br/>"));

$thankyoustr = "Thank you for registering to take <i>$course->shortname - $course->fullname</i> on <i><strong>$SITE->fullname</strong></i>. Please retain a copy of this payment receipt for your records.";
$pdf->writeHTML(utf8_decode($thankyoustr . "<p/>"));

$receipthtml = "
<strong>Total amount charged on " . userdate($order->timecreated) . ":</strong> " . $order->currency . $order->amount . "<br/>
<strong>Payment method:</strong> " . strtoupper($order->paymentmethod) . "<br/>
<strong>CC Number:</strong> xxxx-xxxx-xxxx-" . str_pad($order->refundinfo, 4, '0', STR_PAD_LEFT) . "<p/>
    ";

$pdf->writeHTML($receipthtml);

$footerhtml = get_config('enrol_authorize', 'receipt_footerhtml');
if (!empty($footerhtml)) {
    $pdf->writeHTML($footerhtml);
}
$pdf->Output('receipt.pdf', 'I');
?>
