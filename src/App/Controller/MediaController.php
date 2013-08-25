<?php

namespace App\Controller;

use Silex\ControllerProviderInterface,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\Validator\Constraints as Assert,
    App\Exception\MediaElementNotFound;


/**
 * Summary :
 *  -> __construct
 *  -> connect
 *
 *  -> ONLY FOR CREATION MULTIPLE :
 *      => initCreateMultiple
 *      => getUploadParams      [protected]
 *      => upload               [ajax]
 *      => deleteUploaded       [ajax]
 *
 *  -> ONLY FOR UPDATING MULTIPLE :
 *      => initUpdateMultiple
 *
 *  -> POSTING / DELETING :
 *      => postMultiple
 *      => deleteMultiple
 */
class MediaController implements ControllerProviderInterface
{
    use TaggedDatedTrait;

    const MODULE = 'media';

    // On "home" page, max number of elements
    protected $limitInHome;


    public function __construct(\App\Application $app)
    {
        $this->limitInHome = $app['debug'] ? 10 : 50;

        $this->app             = $app;
        $this->config          = $app['media.config'];
        $this->webPath         = $app['path.web'];
        $this->repository      = $app['model.repository.media'];
        $this->factory         = $app['model.factory.media'];
        $this->factoryUploaded = $app['model.factory.media.uploaded'];
    }

    public function connect(\Silex\Application $app)
    {
        $media = $app['controllers_factory'];

        // Home page
        $this->addHomeRoutes($media);

        // Create multiple
        $media->get('create' , [$this, 'initCreateMultiple']);
        $media->post('create', [$this, 'postMultiple'])
            ->value('isCreation', true);

        // Upload a single file
        $media->post('upload', [$this, 'upload'])
            ->mustBeAjax();

        // Delete a single uploaded file
        $media->get('delete-uploaded/{id}', [$this, 'deleteUploaded'])
            ->mustBeAjax();

        // Update multiple
        $media->post('init-update', [$this, 'initUpdateMultiple']);
        $media->post('update'     , [$this, 'postMultiple'])
            ->value('isCreation', false);

        // Delete multiple
        $media->post('delete', [$this, 'deleteMultiple']);

        return $media;
    }


    /***************************************************************************
     * ONLY FOR CREATION MULTIPLE
     **************************************************************************/

    public function initCreateMultiple()
    {
        $this->repository->collectGarbage();

        return $this->app->render('media/post-multiple.html.twig',
            $this->getUploadParams() + ['isCreation' => true]
        );
    }

    /**
     * Parameters used to upload a file.
     */
    protected function getUploadParams()
    {
        return [
            'acceptFileTypes' => $this->config['acceptTypes.jsRegexp'],
            'maxFileSize'     => $this->config['maxFileSize'],
            'previewSize'     => $this->config['image.thumb.size'],
        ];
    }

    /**
     * Upload a single file through Ajax.
     */
    public function upload(Request $request)
    {
        $media = $this->factoryUploaded->instantiate();

        $errors = $this->factoryUploaded->bind($media, $request->files->get('files'));

        if (! $errors) {
            $this->repository->store($media, true);
        }

        return $this->app->json(['files' =>
            [[
                'view' => $this->app->renderView(
                    'media/'.($errors ? 'uploading-error' : 'post-one-media').'.html.twig',
                    $media
                ),
                'hasError' => (bool) $errors,
            ]]
        ]);
    }

    /**
     * If $id matches with an uploaded media, delete it.
     */
    public function deleteUploaded($id)
    {
        if (! $this->repository->deleteById($id, true))
        {
            $this->app->abort(404, "\"$id\" is not a valid uploaded media element.");
        }
        return '';
    }


    /***************************************************************************
     * ONLY FOR UPDATING MULTIPLE
     **************************************************************************/

    public function initUpdateMultiple(Request $request)
    {
        $countUnfound = 0;

        $listMedia = [];

        $httpData = $request->request->all();  // http POST data

        // Retrieve all elements to update
        foreach ($httpData as $id => $value)
        {
            try {
                $listMedia[] = $this->repository->getById($id);
            }
            catch (MediaElementNotFound $e)
            {
                $countUnfound ++;
            }
        }

        if ($countUnfound > 0)
        {
            $this->app->addFlash('warning', $this->app->transChoice(
                'media.unfound', $countUnfound, [$countUnfound])
            );
        }

        return $this->app->render('media/post-multiple.html.twig', [
            'isCreation' => false,
            'listMedia'  => $listMedia,
        ]);
    }


    /***************************************************************************
     * POSTING / DELETING
     **************************************************************************/

    public function postMultiple($isCreation, Request $request)
    {
        $mediaInError = [];
        $httpData     = $request->request->all(); // http POST data

        // Some counters
        $countInError = 0;
        $countPosted  = 0;
        $countUnfound = 0;

        // Process each posted element
        foreach ($httpData as $id => $httpMedia)
        {
            try {
                $media = $this->repository->getById($id, $isCreation);

                $errors = $this->factory->bind($media, $httpMedia);

                if ($errors)
                {
                    $countInError ++;
                    $mediaInError[] = $media + ['errors' => $errors];
                }
                else
                {
                    $countPosted ++;
                    $this->repository->store($media);
                }
            }
            catch (MediaElementNotFound $e)
            {
                $countUnfound ++;
            }
        }

        // Display the counters in flash messages

        if ($countInError > 0)
        {
            $this->app->addFlash('danger', $this->app->transChoice(
                'media.inError', $countInError, [$countInError]
            ));
        }

        if ($countPosted > 0)
        {
            $this->app->addFlash('success', $this->app->transChoice(
                $isCreation ? 'media.created' : 'media.updated',
                $countPosted, [$countPosted]
            ));
        }

        if ($countUnfound > 0)
        {
            $this->app->addFlash('warning', $this->app->transChoice(
                'media.unfound', $countUnfound, [$countUnfound])
            );
        }

        return (0 === $countInError) ?

            $this->app->redirect('/media') :

            $this->app->render('media/post-multiple.html.twig', $this->getUploadParams() + [
                'isCreation'   => $isCreation,
                'listMedia'    => $mediaInError,
            ]);
    }

    public function deleteMultiple(Request $request)
    {
        $countUnfound = 0;
        $countDeleted = 0;

        $httpData = $request->request->all();  // http POST data

        // Retrieve all elements to delete
        foreach ($httpData as $id => $value)
        {
            if ($this->repository->deleteById($id)) { $countDeleted ++; }
            else                                    { $countUnfound ++; }
        }

        if ($countDeleted > 0)
        {
            $this->app->addFlash('success', $this->app->transChoice(
                'media.deleted', $countDeleted, [$countDeleted])
            );
        }

        if ($countUnfound > 0)
        {
            $this->app->addFlash('warning', $this->app->transChoice(
                'media.unfound', $countUnfound, [$countUnfound])
            );
        }

        return $this->app->redirect('/media');
    }
}
