<?php
require_once 'class.Prototype.php';

$app = new Prototype( ['id' => 'Worker'] );
$app->logging = true;
$app->init();
$app->get_scheme_from_db( 'entry' );

$terms = [
    'rev_type' => 0,
    'workspace_id' => 5,
];
$args = [
];
$objects = $app->db->model( 'entry' )->load( $terms, $args );
foreach ( $objects as $object ) {
    $block_data = json_decode( $obj->block_edit, false );
    $text = '';

    foreach ( $block_data as $block ) {
        if ( $block->type === 'FAQMarkdown' ) {
            $html = Markdown::defaultTransform( $block->text );
            $text .= html_entity_decode( strip_tags( $html ) );
        } elseif ( $block->type === 'FAQText' ) {
            $text .= html_entity_decode( strip_tags( $block->text ) );
        } elseif ( $block->type === 'FAQCode' ) {
            $text .= $block->code;
        }
    }

    $obj->search_text( $text );
    $object->save();
}
