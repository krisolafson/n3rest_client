<?php

require 'vendor/autoload.php';
include_once 'src/util.php';

use Acquia\Hmac\Guzzle\HmacAuthMiddleware;
use Acquia\Hmac\Key;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\Yaml\Yaml;


if (!file_exists('config/config.local.yaml')) {
    exit('Configuration file not found. Please create one located at config/config.local.yaml');
}

$config = Yaml::parse(file_get_contents('config/config.local.yaml'));

if (empty($config['servers'])) {
    exit('Configuration file does not contain any servers.');
}

$servers = $config['servers'];
$methods = ['delete', 'get', 'post', 'put'];

// First server in config.local.yaml is the default. Probably best to use vm there.
$chosen_server = (isset($_REQUEST['server']) && isset($servers[$_REQUEST['server']])) ? $_REQUEST['server'] : key($servers);
$location      = $servers[$chosen_server]['url'];
$is_prod       = strpos($location, 'acquia.com')  !== FALSE;
$url           = isset($_REQUEST['url']) ? str_replace('*', '%2A', $_REQUEST['url']) : '/api/applications';
$full_url      = $location . $url;
$method        = isset($_REQUEST['method']) ? strtolower($_REQUEST['method']) : 'get';
$body          = [];

if ($_POST) {
    foreach ($_POST['keys'] as $key => $key_name) {
        if ($key_name) {
            $body[$key_name] = isset($_POST['values'][$key]) ? $_POST['values'][$key] : null;
        }
    }
}
$json_body = isset($_REQUEST['body']) ? $_REQUEST['body'] : '';
$color     = $is_prod ? '#F00' : '#000';
$preamble  = [];

$key_id = $servers[$chosen_server]['key'];
$secret = $servers[$chosen_server]['secret'];

parse_str($_SERVER['QUERY_STRING'], $params);

if (isset($params['method'])) {
    unset($params['method']);
}
$method_change_url = $_SERVER['SCRIPT_NAME'] . '?' . urldecode(http_build_query($params));

if ($is_prod) {
    echo '<h1 style="color: ' . $color . '">PROD</h1>';
}
echo '<div class="el-select-list__options">';
echo '<div class="md-virtual-repeat-scroller">';
echo '<form name="bodyform" method="post" action="#">';

echo '<select name="method" onchange="toggleForm(this.value);">';
foreach ($methods as $m) {
    echo '<option value="' . $m . '"';
    echo $m == $method ? ' selected="selected"' : '';
    echo '>' . strtoupper($m) . '</option>';
}
echo '</select>';

echo '<select name="server">';
foreach ($servers as $name => $server) {
    echo '<option value="' . $name . '"';
    echo $name == $chosen_server ? ' selected="selected"' : '';
    echo '>(' . $name . ') ' . $server['url'] . '</option>';
}
echo '</select>';

echo '<input type="text" name="url" id="url" value="' . $url . '" size="150" />';

echo ($method == 'post' || $method == 'put') ?  '<fieldset id="body-fields-container" style="">' : '<fieldset id="body-fields-container" style="display: none;">';

echo '<legend>Body</legend><div id="body-fields">';

foreach ($body as $key => $value) {
    echo '<div><input type="text" size="15" class="form-control" placeholder="Key" name="keys[]" value="' . $key . '">';
    echo '<input type="text" size="35" class="form-control" placeholder="Value" name="values[]" value="' . $value . '">';
    echo '<br /></div>';
}

// Always have an empty/extra field
echo '<div><input type="text" size="15" class="form-control" placeholder="Key" name="keys[]" value="">';
echo '<input type="text" size="35" class="form-control" placeholder="Value" name="values[]" value="">';
echo '<br /></div>';

echo '</div>';
echo '<br /><input type="button" value="Add Field" onclick="addField()">';
echo '</fieldset>';

echo '<div class="el-card__footer__actions">';
echo '<button type="submit" class="el-button el-button--primary" value="Submit">Submit</button></div>';
echo '</div>';
echo '</form>';
echo '</div>';

$key = new Key($key_id, $secret);
$middleware = new HmacAuthMiddleware($key);

$stack = HandlerStack::create();
$stack->push($middleware);

$client = new Client([
    'handler' => $stack,
]);
if (!$_POST && $method != 'get') {
    echo '<h3><em>No request sent</em></h3>';
    exit;
}

$start_time = microtime(true);

try {
    switch ($method) {
        case 'post':
            $response = $client->post($full_url, [
                'verify' => false,
                'json' => $body
            ]);

            break;
        case 'put':
            $response = $client->put($full_url, [
                'verify' => false,
                'json' => $body
            ]);

            break;
        case 'delete':
            $response = $client->delete($full_url, [
                'verify' => false,
            ]);

            break;
        case 'get':
        default:
            $response = $client->get($full_url, ['verify' => false]);

            break;
    }
}
catch (Exception $exception) {

    if ($exception instanceof \Acquia\Hmac\Exception\MalformedResponseException) {
        echo '<strong>Exception: </strong>MalformedResponseException<br />';
        $tags = preg_match_all('@<(\w+)\b.*?>.*?</\1>@si', $exception->getResponse()->getBody(), $matches);

        // Dump our html (usually debug var_dumps).
        if ($tags) {
            foreach ($matches[0] as $match) {
                $preamble[] = "<pre>Malformed Response:\n" . $match . '</pre>';
            }
        }

        // Get rid of the tags and forward the body along.
        $body = preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $exception->getResponse()->getBody());
        $response = new \GuzzleHttp\Psr7\Response($exception->getResponse()->getStatusCode(), $exception->getResponse()->getHeaders(), $body);
    }
    else {
        echo '<div class="md-virtual-repeat-scroller">';
        echo '<strong>Error Code: </strong>' . $exception->getResponse()->getStatusCode() . '<br />';
        echo '<strong>Reason: </strong>' . $exception->getResponse()->getReasonPhrase();
        echo '</div>';

        if (!is_json($exception->getResponse()->getBody())) {
            echo '<div>';
            print $exception->getResponse()->getBody();
            echo '</div>';
        }
        else {
            echo '<pre>';
            print $exception->getResponse()->getBody();
            echo '</pre>';
        }
        echo '</body>';
        echo '</html>';
        exit;
    }

}
$end_time = microtime(true);
echo '<div style="color: ' . $color . '">';

$base_url = $_SERVER['SCRIPT_NAME'] . '?server=' . $chosen_server;

echo '</div>';
echo '<div class="md-virtual-repeat-scroller">';
$load_time = number_format($end_time - $start_time, 5);

if ($load_time < 1) {
    $load_color = '#3A8002';
}
elseif ($load_time < 2) {
    $load_color = '#000000';
}
else {
    $load_color = '#CC0000';
}

echo '<strong style="color: ' . $load_color . ';">Load Time: </strong>' . number_format($end_time - $start_time, 5) . ' sec.<br />';
foreach ($response->getHeaders() as $key => $header) {
    echo '<strong>' . $key . '</strong>: ' . implode(", ", $header) . '<br />';
}
echo '</div>';


// Show any preamble content.
echo implode('<br />', $preamble);


if (!is_json($response->getBody())) {
    echo '<div>';
    print $response->getBody();
    echo '</div>';
}
else {
    echo '<pre>';
    $result = json_decode($response->getBody());
    $display = stripslashes($response->getBody());
    $display = auto_link_text($display, $base_url);

    echo $display;

    echo '</pre>';
}

echo '</div>';
echo '</body>';
echo '</html>';

