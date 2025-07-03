<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Bells;

use App\Forms\FormFactory;
use App\Forms\ImageFactory;
use App\Model\Bell\BellRepository;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Html;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use App\Forms\StorageManager;
use Throwable;

class WorkshopForm
{
    public function __construct(
        private readonly FormFactory    $formFactory,
        private readonly BellRepository $bellRepository,
        private readonly ImageFactory   $imageFactory,
        private readonly StorageManager $storageManager
    ) {
    }

    public function create(?int $workshopId, callable $onSuccess): Form
    {
        $form = $this->formFactory->create();
        $isEdit = $workshopId !== null;
        $defaults = [];
        $workshop = null;

        if ($isEdit) {
            $workshop = $this->bellRepository->getWorkshopById($workshopId);
            if ($workshop) {
                $defaults = $workshop->toArray();
            } else {
                throw new BadRequestException('Autor not found.');
            }
        }

        $form->addHidden('workshop_id', $workshopId);

        $form->addText('workshop_name', Html::el()->setHtml('<i class="bi bi-tools me-2"></i>Autor:'))
            ->setRequired('Prosím, vyplň jméno autora')
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Název, např. „Rudolf Perner“');

        $form->addTextArea('workshop_note', Html::el()->setHtml('<i class="bi bi-pencil-square me-2"></i>Poznámka:'))
            ->setRequired(false)
            ->setHtmlAttribute('rows', 4)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Doplňující informace k autorovi (nepovinné)...');

        $uploadLabel = Html::el()->setHtml('<i class="bi bi-image me-2"></i>Fotografie (Volitelné):');
        $form->addUpload('workshop_photo_path', $uploadLabel)
            ->setNullable()
            ->addRule(Form::IMAGE, 'Ups! Povolené jsou pouze obrázky typu JPEG, PNG, GIF, nebo WebP.')
            ->addRule(Form::MAX_FILE_SIZE, 'Pozor! Obrázek je příliš velký (max 5MB).', 5 * 1024 * 1024)
            ->setHtmlAttribute('class', 'form-control')
            ->setOption('description', Html::el('small')->class('form-text text-muted')->setHtml(
                ($isEdit && !empty($workshop->workshop_photo_path))
                    ? 'Stávající fotka existuje. Nahráním nové ji nahradíte. ✨'
                    : 'Fotka není povinná, ale může pomoci. 🖼️'
            ));

        $submitCaption = $isEdit ? '💾 Uložit změny' : '🚀 Přidat autora';
        $submitClass = 'btn ' . ($isEdit ? 'btn-primary' : 'btn-success') . ' w-100 mt-4';
        $form->addSubmit('save', $submitCaption)
            ->setHtmlAttribute('class', $submitClass);

        $form->addProtection('Platnost bezpečnostního tokenu vypršela. Prosím, odešlete formulář znovu.');

        $form->setDefaults($defaults);

        $form->onSuccess[] = function (Form $form, $values) use ($onSuccess, $isEdit, $workshopId, $workshop): void {
            $dbData = [
                'workshop_name' => $values->workshop_name,
                'workshop_note' => $values->workshop_note ?: null,
            ];
            $dbOperationSuccess = false;

            $photoUploaded = $values->workshop_photo_path instanceof FileUpload && $values->workshop_photo_path->isOk() && $values->workshop_photo_path->isImage();

            if ($photoUploaded) {
                try {
                    if ($isEdit && !empty($workshop->workshop_photo_path)) {
                        $this->deleteExistingFile($workshopId, 'workshop_photo_path');
                    }
                    $newPhotoPath = $this->imageFactory->processImage($values->workshop_photo_path, 'workshops');

                    $dbData['workshop_photo_path'] = $newPhotoPath;

                } catch (\Exception $e) {
                    $form['workshop_photo_path']->addError('Bohužel, došlo k chybě při nahrávání fotky. Zkuste to prosím znovu.');
                    return;
                }
            } elseif (!$isEdit) {
                $dbData['workshop_photo_path'] = null;
            }

            try {
                $dataToUpdate = $dbData;
                if ($isEdit) {
                    foreach ($dataToUpdate as $key => $value) {
                        if (isset($workshop->$key) && $workshop->$key === $value) {
                            unset($dataToUpdate[$key]);
                        }
                    }
                }

                if ($isEdit) {
                    if (!empty($dataToUpdate)) {
                        $this->bellRepository->updateWorkshop($workshopId, $dataToUpdate);
                    }
                } else {
                    $this->bellRepository->addWorkshop($dbData);
                }

                $dbOperationSuccess = true;

            } catch (Throwable $e) {
                $form->addError('Ach ne! Něco se pokazilo při ukládání autora. Zkuste to prosím znovu.');
            }

            if ($dbOperationSuccess) {
                try {
                    $onSuccess($values);
                } catch (AbortException $ab) {
                    throw $ab;
                } catch (Throwable $e) {
                    $form->addError('Autor byl připraven, ale došlo k chybě v posledním kroku.');
                }
            }
        };

        return $form;
    }

    private function deleteExistingFile(int $workshopId, string $fieldName): void
    {
        $existingWorkshop = $this->bellRepository->getWorkshopById($workshopId);
        if ($existingWorkshop && isset($existingWorkshop->$fieldName) && !empty($existingWorkshop->$fieldName)) {
            $filePath = $existingWorkshop->$fieldName;
            if ($this->storageManager->fileExists($filePath)) {
                $this->storageManager->deleteFileOrImage($filePath);
            }
        }
    }
}