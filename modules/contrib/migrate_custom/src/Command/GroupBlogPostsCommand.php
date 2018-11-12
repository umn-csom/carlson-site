<?php

namespace Drupal\migrate_custom\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * Class GroupBlogPostsCommand.
 *
 * @DrupalCommand (
 *     extension="migrate_custom",
 *     extensionType="module"
 * )
 */
class GroupBlogPostsCommand extends ContainerAwareCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('migrate_custom:group_blog_posts')
      ->setDescription($this->trans('commands.migrate_custom.group_blog_posts.description'));
  }

 /**
  * {@inheritdoc}
  */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    //$this->getIo()->info('initialize');
  }

 /**
  * {@inheritdoc}
  */
  protected function interact(InputInterface $input, OutputInterface $output) {
    //$this->getIo()->info('interact');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    // Define.
    $connection = \Drupal::database();
    $uuid_service = \Drupal::service('uuid');
    $blog_groups = array();

    // Blog posts by field_blog_group query.
    $blog_posts_query = $connection->query("SELECT * FROM node__field_blog_group");
    $blog_posts = $blog_posts_query->fetchAll();

    // Get all the groups.
    $groups_query = $connection->query("SELECT * FROM groups_field_data");
    $groups = $groups_query->fetchAll();

    // Define the blog group ids.
    if( !empty($groups) ) {
      foreach($groups as $group) {
        $blog_groups[] = array(
          'gid' => $group->id,
          'label' => $group->label,
          'slug' => preg_replace( '/[^a-z]/', '_', ( strtolower($group->label) . '_post' ) )
        );
      }
    }

    // Inject the blog_group data into the group_content_field_data.
    if( !empty($blog_posts) ) {
      $cnt = 2000;
      $result = $connection->insert('group_content_field_data')->fields([
        'id', 'type', 'langcode', 'default_langcode', 'uid', 'gid',
        'label', 'entity_id', 'created', 'changed'
      ]);

      $result2 = $connection->insert('group_content')->fields(['id', 'type', 'uuid', 'langcode']);

      foreach($blog_posts as $each_post) {
        if( !$each_post->deleted ) {
          $gid = $this->_getGroupIdBySlug($each_post->field_blog_group_value, $blog_groups);
          $blog_title = $this->_getBlogTitle($each_post->entity_id);
          $uuid = $uuid_service->generate();

          if( !$this->_isGroupField($each_post->entity_id) ) {
            $record = [
              $cnt, 'blogs-group_node-blog_entry', 
              $each_post->langcode, 1, 11, (int)$gid, $blog_title,
              $each_post->entity_id,
              REQUEST_TIME, REQUEST_TIME
            ];
  
            $record2 = [$cnt, 'blogs-group_node-blog_entry', $uuid, $each_post->langcode];
  
            $result->values($record);
            $result2->values($record2);
            $cnt++;
          }
        }
      }

      if( $result->count() > 0 && $result2->count() > 0 ) {
        $result->execute();
        $result2->execute();
        $this->getIo()->info($this->trans('commands.migrate_custom.group_blog_posts.messages.success'));
      } else {
        $this->getIo()->info($this->trans('commands.migrate_custom.group_blog_posts.messages.failure'));
      }
    }
  }

  protected function _getGroupIdBySlug($slug, $data) {
    foreach($data as $item) {
      if($item['slug'] === $slug) {
        return $item['gid'];
      }
    }
  }

  protected function _getBlogTitle($nid) {
    $connection = \Drupal::database();
    $node_query = $connection->query("SELECT * FROM node_field_data WHERE nid=" . $nid);
    $node = $node_query->fetch();

    if( isset($node) ) {
      return $node->title;
    }
  }

  protected function _isGroupField($entity_id) {
    $connection = \Drupal::database();
    $node_query = $connection->query("SELECT * FROM group_content_field_data WHERE entity_id=" . $entity_id);
    $node = $node_query->fetch();
    return $node;
  }
}
