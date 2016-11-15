<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>N3 REST API</title>
    <style type="text/css" media="all">
        @import url("https://cloud.acquia.com/sites/all/themes/elemental-drupal-theme/assets/styles/styles.css");
        @import url("https://cloud.acquia.com/sites/all/themes/elemental_n3/assets/styles/elemental_n3.css");
    </style>
    <script type="text/javascript">
        function addField() {
            var html = '<input type="text" size="15" class="form-control" placeholder="Key" name="keys[]" value="">' +
                '<input type="text" size="35" class="form-control" placeholder="Value" name="values[]" value=""><br />';
            var newChild = document.createElement('div');
            newChild.innerHTML = html;
            document.getElementById('body-fields').appendChild(newChild);
        }

        function toggleForm(method) {
            if (method == 'post' || method == 'put') {
                document.getElementById('body-fields-container').style.display = 'block';
            }
            else {
                document.getElementById('body-fields-container').style.display = 'none';
            }
        }
    </script>
</head>
<body style="padding: 1rem; overflow: scroll;">
<?php


/**
 * Check if a string is proper json format.
 *
 * @param $string
 *
 * @return bool
 *
 */
function is_json($string)
{
    json_decode($string);

    return json_last_error() === JSON_ERROR_NONE;
}


/**
 * Replace links in text with html links.
 * @see: http://daringfireball.net/2010/07/improved_regex_for_matching_urls
 *
 * @param  string $text
 *   The text to convert to links.
 * @param  string $base_url
 *   The base URL to redirect to.
 *
 * @return string
 *   A string with all URL (as much as possible) converted to links.
 *
 */
function auto_link_text($text, $base_url) {

    $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/|})))#';

    return preg_replace_callback($pattern, function ($matches) use ($base_url) {

        $url_full = $matches[0];
        $url_show = $url_full;
        $url_full = str_replace(['{', '}'], ['', ''], $url_full);
        $url_full = strpos($url_full, '//') === false ? '//' . $url_full : $url_full;

        return strpos($url_full, 'cloud.acquia') !== false ?
            "<a rel=\"nofollow\" href=\"$base_url&url=$url_full\">$url_show</a>" :
            "<a rel=\"nofollow\" target=\"_blank\" href=\"$url_full\">$url_show</a>";
    }, $text);
}
