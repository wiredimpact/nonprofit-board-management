<?php
/**
 * WI_Board_Committees allows the board to create committees and add board members to each of those committees.
 *
 * @author Wired Impact
 */
class WI_Board_Committees {
  
  public function __construct() {
    //Create our board committees custom post type
    add_action( 'init', array( $this, 'create_board_committees_type' ) );
    add_action( 'admin_init', array( $this, 'create_board_committees_meta_boxes' ) );
    add_action( 'save_post', array( $this, 'save_board_committees_meta' ), 10, 2 );
    
    //Handle meta capabilities for our board_committees custom post type.
    add_filter( 'map_meta_cap', array( $this, 'board_committees_map_meta_cap' ), 10, 4 );
    
    //Adjust the columns and content shown when viewing the board committees post type list.
    add_filter( 'manage_edit-board_committees_columns', array( $this, 'edit_board_committees_columns' ) );
    add_action( 'manage_board_committees_posts_custom_column', array( $this, 'show_board_committee_columns' ), 10, 2 );
  }

 /*
   * Create our board committees post type.
   */
  public function create_board_committees_type(){
    $labels = array(
      'name' => 'Board Committees',
      'singular_name' => 'Board Committee',
      'add_new' => 'Add New Board Committee',
      'add_new_item' => 'Add New Board Committee',
      'edit_item' => 'Edit Board Committee',
      'new_item' => 'New Board Committee',
      'all_items' => 'All Board Committees',
      'view_item' => 'View Board Committee',
      'search_items' => 'Search Board Committees',
      'not_found' =>  'No board committees found',
      'not_found_in_trash' => 'No board committees found in trash', 
      'parent_item_colon' => '',
      'menu_name' => 'Board Committees'
    );

    $args = array(
      'labels' => $labels,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => false, //Done through add_submenu_page for more flexibility
      'query_var' => false,
      'capability_type' => 'board_committee',
      'capabilities' => array(
          'publish_posts' => 'publish_board_committees',
          'edit_posts' => 'edit_board_committees',
          'edit_others_posts' => 'edit_others_board_committees',
          'delete_posts' => 'delete_board_committees',
          'delete_others_posts' => 'delete_others_board_committees',
          'read_private_posts' => 'read_private_board_committees',
          'edit_post' => 'edit_board_committee',
          'delete_post' => 'delete_board_committee',
          'read_post' => 'read_board_committee'
      ),
      'supports' => array( 'title' )
    ); 
    
    register_post_type( 'board_committees', $args );
  }
  
  
  /*
   * Handle meta capabilities for our board_committees custom post type
   */
  public function board_committees_map_meta_cap( $caps, $cap, $user_id, $args ){
    //If editing, deleting, or reading a board committee, get the post and post type object.
    if ( 'edit_board_committee' == $cap || 'delete_board_committee' == $cap || 'read_board_committee' == $cap ) {
     $post = get_post( $args[0] );
     $post_type = get_post_type_object( $post->post_type );

     $caps = array();
    }

    //If editing a board_committee, assign the required capability.
    if ( 'edit_board_committee' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->edit_posts;
     else
      $caps[] = $post_type->cap->edit_others_posts;
    }

    //If deleting a board_committee, assign the required capability.
    elseif ( 'delete_board_committee' == $cap ) {
     if ( $user_id == $post->post_author )
      $caps[] = $post_type->cap->delete_posts;
     else
      $caps[] = $post_type->cap->delete_others_posts;
    }

    //If reading a private board_committee, assign the required capability.
    elseif ( 'read_board_committee' == $cap ) {
     if ( 'private' != $post->post_status )
      $caps[] = 'read';
     elseif ( $user_id == $post->post_author )
      $caps[] = 'read';
     else
      $caps[] = $post_type->cap->read_private_posts;
    }

    //Return the capabilities required by the user.
    return $caps;
  }  
}//WI_Board_Committees