<?php

/*
Plugin Name: ゲンシジン
Plugin URI: https://github.com/ym405nm/wp-genshijin
Description: ゲンシジン ブログ トウコウ スル
Author: yoshinori matsumoto (genshijin)
Version: 0.1
Author URI: https://twitter.com/ym405nm
*/


add_action('new_to_publish', 'genshijin_okiru');
add_action('draft_to_publish', 'genshijin_okiru');
add_action('auto-draft_to_publish', 'genshijin_okiru');
function genshijin_okiru($post)
{

    $client_id = '我……アイディ……入レル';
    $client_secret = 'シークレット……入レル……！';

    $token_url = 'https://api.ce-cotoha.com/v1/oauth/accesstokens';

    $option_name = 'genshijin_token';

    // アクセストークン取得
    $access_token = get_option($option_name);
    if (!$access_token) {
        $args = array(
            'headers' => array(
                'content-type' => 'application/json'
            ),
            'body' => json_encode(
                array(
                    'grantType' => 'client_credentials',
                    'clientId' => $client_id,
                    'clientSecret' => $client_secret
                )
            )
        );

        $token_result = wp_remote_post($token_url, $args);
        $token_body = $token_result['body'];
        $access_token = json_decode($token_body)->access_token;
        update_option($option_name, $access_token);
    }

    // パース
    $new_post = $post;

    // タイトル
    $new_post->post_title = get_genshijin($access_token, $post->post_title);

    // 内容
    $post_content = $post->post_content;
    preg_match_all("/<p>(.*?)<\/p>/i", $post_content, $matches);
    $post_content = $matches[1][0];

    $new_post->post_content = get_genshijin($access_token, $post_content);

    wp_update_post($new_post);
}

function get_genshijin($access_token, $content)
{

    $parse_url = 'https://api.ce-cotoha.com/api/dev/nlp/v1/parse';
    $parse_arg = array(
        'headers' => array(
            'content-type' => 'application/json',
            "charset" => "UTF-8",
            "Authorization" => sprintf("Bearer %s", $access_token)
        ),
        'body' => json_encode(
            array(
                'sentence' => $content,
                'type' => 'default'
            )
        )
    );
    $parse_response = wp_remote_post($parse_url, $parse_arg);
    $parse_body = $parse_response['body'];
    $parse_array = json_decode($parse_body, true);
    $result_array = array();
    foreach ($parse_array["result"] as $parse_result) {
        foreach ($parse_result['tokens'] as $parse_tokens) {
            $hinshi = $parse_tokens['pos'];
            if ("格助詞" != $hinshi && "連用助詞" != $hinshi &&
                "引用助詞" != $hinshi && "終助詞" != $hinshi) {
                $result_array[] = $parse_tokens['kana'];
            }

        }
    }
    return implode(' ', $result_array);

}
