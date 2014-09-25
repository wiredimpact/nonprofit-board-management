<?php
/*
 * List Board Members Shortcode Template
 * 
 * This template is used when someone uses the [list_board_members] shortcode within one of their pages
 * or posts.  You can customize the content that is shown by copying this file to your theme folder
 * and adjusting the code below.  You will need basic knowledge of PHP, HTML and CSS to work
 * with this file.
 */
?>
<!-- CSS used for basic board members styling -->
<style type='text/css'>
.board-member { width: 85%; margin-bottom: 3%; }  
.board-member img { float: left; display: block; margin: 0 2% 1% 0; }
.board-member .job-employer { font-size: 70%; }
</style>

<div class="board-members">
  
  <?php foreach( $board_members as $board_member ): //Loop through all the board members ?>
    
    <div class="board-member">
    
      <?php
      //Store user meta information to use below for display
      $name         = $board_member->display_name;
      $job_title    = get_user_meta( $board_member->ID, 'job_title', true ); //Store job title
      $employer     = get_user_meta( $board_member->ID, 'current_employer', true ); //Store current employer
      $biography    = get_user_meta( $board_member->ID, 'description', true ); //Store biography
      $email        = $board_member->user_email; //Store email, not shown by default
      //$phone      = get_user_meta( $board_member->ID, 'phone', true ); //Store phone, not shown by default
      //$committees = WI_Board_Committees::get_user_committees( $board_member->ID ); //Store committees, not shown by default
      ?>   

      <?php
      //Gravatar photo from http://en.gravatar.com/
      if( $this->validate_gravatar( $email ) ){
        echo get_avatar( $board_member->ID, '75' );
      }
      ?>
      
      <h3>
        <?php echo $name; //Name ?><br />
        <span class="job-employer">
          <?php
          //Job and employer
          echo $job_title;
          if( $job_title != '' && $employer != '' ){
            echo ', ';
          }
          echo $employer;
          ?>
        </span>
      </h3>
      
      <p>
        <?php echo $biography; ?>
      </p>
    
    </div><!-- /.board-member -->
    
  <?php endforeach; ?>
  
</div><!-- /.board-members -->