<?php

namespace App\UI\Back\Post;

use App\Forms\FormFactory;
use Nette\Application\UI\Form;
use App\Model\News\NewsFacade;
use Nette\Security\User;
use App\Forms\StorageManager;
use App\Forms\ImageFactory;
use App\Forms\FileFactory;

class PostFormFactory
{
    public function __construct(
        private FormFactory $formFactory,
        private NewsFacade $facade,
        private User $user,
        private StorageManager $storageManager,
        private ImageFactory $imageFactory,
        private FileFactory $fileFactory
    ) {}

    public function create(callable $onSuccess, ?int $postId = null): Form
    {
        $form = $this->formFactory->create();

        // Přidání polí formuláře
        $form->addText('title', 'Titulek:')
            ->setRequired('Prosím, zadejte titulek.')
            ->addRule(Form::MAX_LENGTH, 'Titulek může být dlouhý maximálně %d znaků.', 100);

        $row = $form->addRow();
        $row->addCell(6)
            ->addUpload('photo', 'Obrázek v textu příspěvku:')
            ->addCondition(Form::FILLED)
            ->addRule(Form::IMAGE, 'Obrázek musí být ve formátu JPEG, PNG, GIF nebo WebP.');

        $row->addCell(6)
            ->addUpload('filePdf', 'Soubor, třeba PDF, ... není povinný:')
            ->addCondition(Form::FILLED)
            ->addRule(Form::MIME_TYPE, 'Soubor musí být ve formátu PDF.', 'application/pdf');


        $form->addTextArea('content', 'Obsah:')
            ->setHtmlAttribute('rows', 10);

            $row = $form->addRow();
            $row->addCell(3)
             ->addSelect('show_bartik', 'časopis: ', [
                 0 => 'žádný',
                 1 => 'Bartík',
                 2 => 'Věstník',
                 3 => 'Youtube',
             ])->setDefaultValue(0)
             ->setHtmlAttribute('class', 'my-3');
            $row->addCell(9)
             ->addText('web', 'odkaz na web:');
        
        
        $form->addSubmit('send', 'Uložit')
            ->setHtmlAttribute('class', 'btn btn-primary');

        $form->addProtection('Prosím, odešlete formulář znovu (vypršel bezpečnostní token).');

        $form->onSuccess[] = function (Form $form, \Nette\Utils\ArrayHash $values) use ($onSuccess, $postId): void {
            // Convert to array for easier manipulation
            $values = (array)$values;
        
            // Handle photo upload
            if (!empty($values['photo']) && $values['photo']->isOk() && $values['photo']->isImage()) {
                if ($postId) {
                    $existingPost = $this->facade->getPostById($postId);
                    // Delete the old photo if it exists
                    if ($existingPost && $existingPost->photo) {
                        $this->storageManager->deleteFileOrImage($existingPost->photo);
                    }
                }
                // Process and set the new photo
                $values['photo'] = $this->imageFactory->processImage($values['photo']);
            } else {
                // Retain the old photo if editing and no new photo is uploaded
                if ($postId) {
                    $existingPost = $this->facade->getPostById($postId);
                    $values['photo'] = $existingPost->photo ?? null;
                } else {
                    unset($values['photo']);
                }
            }
        
            // Handle PDF file upload
            if (!empty($values['filePdf']) && $values['filePdf']->isOk()) {
                if ($postId) {
                    $existingPost = $this->facade->getPostById($postId);
                    // Delete the old file PDF if it exists
                    if ($existingPost && $existingPost->filePdf) {
                        $this->storageManager->deleteFileOrImage($existingPost->filePdf);
                    }
                }
                // Process and set the new file PDF
                $values['filePdf'] = $this->fileFactory->processFile($values['filePdf']);
            } else {
                // Retain the old file PDF if editing and no new file is uploaded
                if ($postId) {
                    $existingPost = $this->facade->getPostById($postId);
                    $values['filePdf'] = $existingPost->filePdf ?? null;
                } else {
                    unset($values['filePdf']);
                }
            }
        
            // Update or add the post based on $postId
            if ($postId) {
                $this->facade->editPost($values, $postId);
            } else {
                $this->facade->addPost($values);
            }
        
            $onSuccess();
        };

        return $form;
    }
}