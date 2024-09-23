<?php namespace hpr_distributor;

function register_press_release_post_type()
{

   // add_action( 'init', function() {
        register_post_type( 'press-release', array(
        'labels' => array(
            'name' => 'Press Releases',
            'singular_name' => 'Press Release',
            'menu_name' => 'Press Release',
            'all_items' => 'All press-release',
            'edit_item' => 'Edit Press Release',
            'view_item' => 'View Press Release',
            'view_items' => 'View press-release',
            'add_new_item' => 'Add New Press Release',
            'add_new' => 'Add New Press Release',
            'new_item' => 'New Press Release',
            'parent_item_colon' => 'Parent Press Release:',
            'search_items' => 'Search press-release',
            'not_found' => 'No press-release found',
            'not_found_in_trash' => 'No press-release found in Trash',
            'archives' => 'Press Release Archives',
            'attributes' => 'Press Release Attributes',
            'insert_into_item' => 'Insert into press release',
            'uploaded_to_this_item' => 'Uploaded to this press release',
            'filter_items_list' => 'Filter press-release list',
            'filter_by_date' => 'Filter press-release by date',
            'items_list_navigation' => 'press-release list navigation',
            'items_list' => 'press-release list',
            'item_published' => 'Press Release published.',
            'item_published_privately' => 'Press Release published privately.',
            'item_reverted_to_draft' => 'Press Release reverted to draft.',
            'item_scheduled' => 'Press Release scheduled.',
            'item_updated' => 'Press Release updated.',
            'item_link' => 'Press Release Link',
            'item_link_description' => 'A link to a press release.',
        ),
        'public' => true,
        'show_in_rest' => true,
        'supports' => array(
            0 => 'title',
            1 => 'author',
            2 => 'trackbacks',
            3 => 'editor',
            4 => 'excerpt',
            5 => 'revisions',
            6 => 'page-attributes',
            7 => 'thumbnail',
            8 => 'custom-fields',
            9 => 'post-formats',
        ),
        'taxonomies' => array(
            0 => 'category',
        ),
        'delete_with_user' => false,
    ) );
//    } );
}
function register_press_release_custom_fields(){
	
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_658a07665ddf5',
	'title' => 'pressrelease',
	'fields' => array(
		array(
			'key' => 'field_658a0794c78bb',
			'label' => 'Original Post',
			'name' => 'original_post',
			'aria-label' => '',
			'type' => 'group',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'block',
			'sub_fields' => array(
				array(
					'key' => 'field_658a07de9760c',
					'label' => 'slug',
					'name' => 'slug',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				),
				array(
					'key' => 'field_658a07e39760d',
					'label' => 'URL',
					'name' => 'url',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				),
			),
		),
		array(
			'key' => 'field_658a079ec78bc',
			'label' => 'Author',
			'name' => 'author',
			'aria-label' => '',
			'type' => 'group',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'layout' => 'block',
			'sub_fields' => array(
				array(
					'key' => 'field_658a07a9c78bd',
					'label' => 'slug',
					'name' => 'slug',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				),
				array(
					'key' => 'field_658a07b9c78be',
					'label' => 'URL',
					'name' => 'url',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				),
				array(
					'key' => 'field_658a07ed9760e',
					'label' => 'ID',
					'name' => 'id',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'maxlength' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
				),
			),
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'press-release',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
}