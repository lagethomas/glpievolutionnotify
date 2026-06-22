<?php

/**
 * GLPI Evolution Notify - Configuration form.
 *
 * @license GPLv2+
 */

declare(strict_types=1);

if (!defined('GLPI_ROOT')) {
    die('Sorry.');
}

require_once(GLPI_ROOT . '/inc/includes.php');

global $DB;

Session::checkRight('config', UPDATE);

$configTable = 'glpi_plugin_evolutionnotify_configs';
$row         = [];

if ($DB->tableExists($configTable)) {
    $iterator = $DB->request(['SELECT' => '*', 'FROM' => $configTable, 'LIMIT' => 1]);
    if (count($iterator) > 0) {
        $row = (array)$iterator->current();
    }
}

Html::header(
    __('Evolution Notify', 'glpievolutionnotify'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugin'
);

$csrfToken = Session::getNewCSRFToken();

echo "<form id='form-config-evolution-notify' method='post'>";

echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Evolution API Configuration', 'glpievolutionnotify') . "</th></tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('API URL', 'glpievolutionnotify') . "</td>";
echo "<td><input type='url' name='api_url' id='cfg_api_url' value='" . htmlspecialchars($row['api_url'] ?? '') . "' size='60' required></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('API Token', 'glpievolutionnotify') . "</td>";
echo "<td><input type='password' name='api_token' id='cfg_api_token' value='" . htmlspecialchars($row['api_token'] ?? '') . "' size='60' required></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('Instance', 'glpievolutionnotify') . "</td>";
echo "<td><input type='text' name='instance' id='cfg_instance' value='" . htmlspecialchars($row['instance'] ?? '') . "' size='40' required></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td colspan='2'><strong>" . __('Events to notify', 'glpievolutionnotify') . "</strong></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('Send on Waiting', 'glpievolutionnotify') . "</td>";
echo "<td><input type='checkbox' name='send_on_waiting' id='cfg_send_on_waiting' value='1' " . (!empty($row['send_on_waiting']) ? 'checked' : '') . "></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('Send on Accepted', 'glpievolutionnotify') . "</td>";
echo "<td><input type='checkbox' name='send_on_accepted' id='cfg_send_on_accepted' value='1' " . (!empty($row['send_on_accepted']) ? 'checked' : '') . "></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td>" . __('Send on Refused', 'glpievolutionnotify') . "</td>";
echo "<td><input type='checkbox' name='send_on_refused' id='cfg_send_on_refused' value='1' " . (!empty($row['send_on_refused']) ? 'checked' : '') . "></td>";
echo "</tr>";

echo "<tr class='tab_bg_2'>";
echo "<td colspan='2' class='center'>";
echo "<button type='button' class='btn btn-primary' id='btn-save-config'>"
    . __('Save', 'glpievolutionnotify') . "</button>";
echo "</td></tr>";

echo "</table>";
echo "</form>";

echo "<div id='config-save-msg' style='display:none;margin-top:10px;padding:10px;border-radius:4px;'></div>";

echo "<br>";

echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>" . __('Test Environment', 'glpievolutionnotify') . "</th></tr>";
echo "<tr class='tab_bg_2'>";
echo "<td>" . __('Send test message to a phone number', 'glpievolutionnotify') . "</td>";
echo "<td class='center'>";
echo "<button type='button' class='btn btn-primary' id='btn-test-evolution-notify'>"
    . __('Test Send', 'glpievolutionnotify') . "</button>";
echo "</td>";
echo "</tr>";
echo "</table>";

echo "<div class='modal fade' id='modal-test-evolution-notify' tabindex='-1'>";
echo "<div class='modal-dialog'><div class='modal-content'>";
echo "<div class='modal-header'><h5 class='modal-title'>" . __('Test Send', 'glpievolutionnotify') . "</h5>";
echo "<button type='button' class='btn-close' data-bs-dismiss='modal'></button></div>";
echo "<div class='modal-body'>";
echo "<label for='test-phone'><b>" . __('Phone number (with DDD):', 'glpievolutionnotify') . "</b></label><br><br>";
echo "<input type='text' id='test-phone' class='form-control' placeholder='11999998888' />";
echo "<br>";
echo "<div id='test-result' style='display:none;padding:10px;border-radius:4px;'></div>";
echo "</div>";
echo "<div class='modal-footer'>";
echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>" . __('Cancel', 'glpievolutionnotify') . "</button>";
echo "<button type='button' class='btn btn-primary' id='btn-send-test-msg'>" . __('Send', 'glpievolutionnotify') . "</button>";
echo "</div></div></div></div>";

$jsLabelTest       = htmlspecialchars(__('Test Send', 'glpievolutionnotify'));
$jsLabelPhone      = htmlspecialchars(__('Phone number (with DDD):', 'glpievolutionnotify'));
$jsLabelEnterPhone = htmlspecialchars(__('Enter a phone number.', 'glpievolutionnotify'));
$jsLabelSending    = htmlspecialchars(__('Sending...', 'glpievolutionnotify'));
$jsLabelCancel     = htmlspecialchars(__('Cancel', 'glpievolutionnotify'));
$jsLabelSend       = htmlspecialchars(__('Send', 'glpievolutionnotify'));
$jsLabelSave       = htmlspecialchars(__('Save', 'glpievolutionnotify'));
$jsLabelSaving     = htmlspecialchars(__('Saving...', 'glpievolutionnotify'));
$jsLabelError      = htmlspecialchars(__('Error:', 'glpievolutionnotify'));
$jsLabelConnError  = htmlspecialchars(__('Connection error:', 'glpievolutionnotify'));
$jsLabelSaved      = htmlspecialchars(__('Configuration saved successfully!', 'glpievolutionnotify'));

echo <<<HTML
<script>
(function() {
    var baseUrl   = window.location.pathname.replace(/\/front\/config\.php$/, '');
    var ajaxUrl   = baseUrl + '/ajax/save_config.php';
    var testUrl   = baseUrl + '/ajax/test_send.php';
    var csrfToken = '{$csrfToken}';

    // --- SAVE CONFIG ---
    $(document).on('click', '#btn-save-config', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('{$jsLabelSaving}');
        $('#config-save-msg').hide();

        var postData = {
            _glpi_csrf_token: csrfToken,
            api_url:          $('#cfg_api_url').val(),
            api_token:        $('#cfg_api_token').val(),
            instance:         $('#cfg_instance').val(),
            send_on_waiting:  $('#cfg_send_on_waiting').is(':checked') ? 1 : 0,
            send_on_accepted: $('#cfg_send_on_accepted').is(':checked') ? 1 : 0,
            send_on_refused:  $('#cfg_send_on_refused').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: postData,
            success: function(data) {
                if (data.ok) {
                    $('#config-save-msg').show()
                        .css({'background':'#d4edda','color':'#155724','border':'1px solid #c3e6cb'})
                        .html('<b>{$jsLabelSaved}</b>');
                } else {
                    $('#config-save-msg').show()
                        .css({'background':'#f8d7da','color':'#721c24','border':'1px solid #f5c6cb'})
                        .html('<b>{$jsLabelError}</b> ' + data.error);
                }
            },
            error: function(xhr) {
                $('#config-save-msg').show()
                    .css({'background':'#f8d7da','color':'#721c24','border':'1px solid #f5c6cb'})
                    .html('<b>{$jsLabelConnError}</b> ' + xhr.statusText);
            },
            complete: function() {
                btn.prop('disabled', false).text('{$jsLabelSave}');
            }
        });
    });

    // ===== TEST SEND =====
    $(document).on('click', '#btn-test-evolution-notify', function() {
        $('#test-phone').val('');
        $('#test-result').hide().html('').removeClass('alert-success alert-danger');
        var el = document.getElementById('modal-test-evolution-notify');
        bootstrap.Modal.getOrCreateInstance(el).show();
    });

    $(document).on('click', '#btn-send-test-msg', function() {
        var phone = $.trim($('#test-phone').val());
        if (phone === '') {
            $('#test-result').show()
                .addClass('alert-danger')
                .html('<b>{$jsLabelEnterPhone}</b>');
            return;
        }
        var btn = $(this);
        btn.prop('disabled', true).text('{$jsLabelSending}');

        $.ajax({
            url: testUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                phone: phone,
                _glpi_csrf_token: csrfToken
            },
            success: function(data) {
                if (data.ok) {
                    $('#test-result').show()
                        .addClass('alert-success').removeClass('alert-danger')
                        .html('<b>' + data.msg + '</b>');
                } else {
                    $('#test-result').show()
                        .addClass('alert-danger').removeClass('alert-success')
                        .html('<b>{$jsLabelError}</b> ' + data.error);
                }
            },
            error: function(xhr) {
                $('#test-result').show()
                    .addClass('alert-danger').removeClass('alert-success')
                    .html('<b>{$jsLabelConnError}</b> ' + xhr.statusText);
            },
            complete: function() {
                btn.prop('disabled', false).text('{$jsLabelSend}');
            }
        });
    });
})();
</script>
HTML;

Html::footer();
