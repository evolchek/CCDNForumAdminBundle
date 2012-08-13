<?php

/*
 * This file is part of the CCDNForum AdminBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNForum\AdminBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 *
 * @author Reece Fowell <reece@codeconsortium.com>
 * @version 1.0
 */
class PostController extends ContainerAware
{

    /**
     *
     * Display a list of deleted posts.
     *
     * @access public
     * @param  Int $page
     * @return RenderResponse
     */
    public function showDeletedAction($page)
    {
        if ( ! $this->container->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You do not have access to this section.');
        }

        $user = $this->container->get('security.context')->getToken()->getUser();

        $postsPager = $this->container->get('ccdn_forum_forum.post.repository')->findDeletedPostsForAdminsPaginated();

        $postsPerPage = $this->container->getParameter('ccdn_forum_admin.post.show_deleted.posts_per_page');
        $postsPager->setMaxPerPage($postsPerPage);
        $postsPager->setCurrentPage($page, false, true);

        // setup crumb trail.
        $crumbs = $this->container->get('ccdn_component_crumb.trail')
            ->add($this->container->get('translator')->trans('ccdn_forum_admin.crumbs.dashboard.admin', array(), 'CCDNForumAdminBundle'), $this->container->get('router')->generate('ccdn_component_dashboard_show', array('category' => 'admin')), "sitemap")
            ->add($this->container->get('translator')->trans('ccdn_forum_admin.crumbs.post.deleted', array(), 'CCDNForumAdminBundle'), $this->container->get('router')->generate('ccdn_forum_admin_post_deleted_show'), "trash");

        return $this->container->get('templating')->renderResponse('CCDNForumAdminBundle:Post:show_deleted.html.' . $this->getEngine(), array(
            'user_profile_route' => $this->container->getParameter('ccdn_forum_moderator.user.profile_route'),
            'user' => $user,
            'crumbs' => $crumbs,
            'posts' => $postsPager,
            'pager' => $postsPager,
        ));
    }

    /**
     *
     * @access public
     * @return RedirectResponse
     */
    public function bulkAction()
    {
        if ( ! $this->container->get('security.context')->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('You do not have access to this section.');
        }

        //
        // Get all the checked item id's.
        //
        $itemIds = array();
        $ids = $_POST;
        foreach ($ids as $itemKey => $itemId) {
            if (substr($itemKey, 0, 6) == 'check_') {
                //
                // Cast the key values to int upon extraction.
                //
                $id = (int) substr($itemKey, 6, (strlen($itemKey) - 6));

                if (is_int($id) == true) {
                    $itemIds[] = $id;
                }
            }
        }

        //
        // Don't bother if there are no flags to process.
        //
        if (count($itemIds) < 1) {
            return new RedirectResponse($this->container->get('router')->generate('ccdn_forum_admin_post_deleted_show'));
        }

        $user = $this->container->get('security.context')->getToken()->getUser();

        $posts = $this->container->get('ccdn_forum_forum.post.repository')->findThesePostsByIdForModeration($itemIds);

        if ( ! $posts || empty($posts)) {
            $this->container->get('session')->setFlash('notice', $this->container->get('translator')->trans('flash.post.no_posts_found', array(), 'CCDNForumModeratorBundle'));

            return new RedirectResponse($this->container->get('router')->generate('ccdn_forum_admin_post_deleted_show'));
        }

        if (isset($_POST['submit_lock'])) {
            $this->container->get('ccdn_forum_admin.post.manager')->bulkLock($posts, $user)->flush();
        }
        if (isset($_POST['submit_unlock'])) {
            $this->container->get('ccdn_forum_admin.post.manager')->bulkUnlock($posts)->flush();
        }
        if (isset($_POST['submit_restore'])) {
            $this->container->get('ccdn_forum_admin.post.manager')->bulkRestore($posts)->flush();
        }
        if (isset($_POST['submit_soft_delete'])) {
            $this->container->get('ccdn_forum_admin.post.manager')->bulkSoftDelete($posts, $user)->flush();
        }
        if (isset($_POST['submit_hard_delete'])) {
            $this->container->get('ccdn_forum_admin.post.manager')->bulkHardDelete($posts)->flush();
        }

        return new RedirectResponse($this->container->get('router')->generate('ccdn_forum_admin_post_deleted_show'));
    }

    /**
     *
     * @access protected
     * @return String
     */
    protected function getEngine()
    {
        return $this->container->getParameter('ccdn_forum_admin.template.engine');
    }

}
