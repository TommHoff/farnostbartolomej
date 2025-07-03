<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Bells;

use App\Forms\FormFactory;
use App\Forms\ImageFactory;
use App\Forms\StorageManager;
use App\Model\Bell\BellRepository;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\Html;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Throwable;
use Nette\Utils\ArrayHash;

class BellsFormFactory
{
    public function __construct(
        private readonly FormFactory      $formFactory,
        private readonly BellRepository   $bellRepository,
        private readonly ImageFactory     $imageFactory,
        private readonly StorageManager   $storageManager
    ) {
    }

    public function create(?int $bellId, callable $onSuccess): Form
    {
        $form = $this->formFactory->create();
        $isEdit = $bellId !== null;
        $defaults = [];
        $bell = null;

        if ($isEdit) {
            $bell = $this->bellRepository->getBellById($bellId);
            if ($bell) {
                $defaults = $bell->toArray();
            } else {
                throw new BadRequestException('Zvon nenalezen.');
            }
        }

        $form->addHidden('bell_id', $bellId);

        $form->addText('bell_name', Html::el()->setHtml('🔔 Název zvonu:'))
            ->setRequired('Prosím, vyplňte název zvonu.')
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Název, např. „Zvon Marie“');

        $workshops = $this->bellRepository->getAllWorshops()->fetchPairs('workshop_id', 'workshop_name');
        $form->addSelect('workshop_Fid', Html::el()->setHtml('🏭 Autor:'), $workshops)
            ->setRequired('Prosím, vyber autora.')
            ->setPrompt('Vyber autora')
            ->setHtmlAttribute('class', 'form-select');

        $form->addText('bell_year', Html::el()->setHtml('📅 Rok odlití:'))
            ->setRequired(false)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Např. „1920“ nebo „cca 18. stol.“');

        $form->addText('bell_tuning', Html::el()->setHtml('🎶 Ladění:'))
            ->setRequired(false)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Např. „c\'“');

        $form->addTextArea('bell_note', Html::el()->setHtml('📝 Poznámka:'))
            ->setRequired(false)
            ->setHtmlAttribute('rows', 3)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Obecné poznámky ke zvonu...');

        // Fetch and prepare places data using the method in BellRepository
        $places = $this->bellRepository->getCalPlace(); // Use the method from BellRepository

        $form->addSelect('place_Fid', Html::el()->setHtml('📍 Umístění:'), $places)
            ->setRequired('Prosím, vyberte umístění.')
            ->setPrompt('Vyberte umístění')
            ->setHtmlAttribute('class', 'form-select');

        $form->addTextArea('bell_extra', Html::el()->setHtml('✨ Extra info:'))
            ->setRequired(false)
            ->setHtmlAttribute('rows', 3)
            ->setHtmlAttribute('class', 'form-control')
            ->setHtmlAttribute('placeholder', 'Další doplňující informace...');

        $uploadLabel = Html::el()->setHtml('📷 Fotografie zvonu (Volitelné):');
        $form->addUpload('bell_photo_path', $uploadLabel)
            ->setNullable()
            ->addRule(Form::IMAGE, 'Ups! Povolené jsou pouze obrázky typu JPEG, PNG, GIF, nebo WebP.')
            ->addRule(Form::MAX_FILE_SIZE, 'Pozor! Obrázek je příliš velký (max 5MB).', 5 * 1024 * 1024)
            ->setHtmlAttribute('class', 'form-control')
            ->setOption('description', Html::el('small')->class('form-text text-muted')->setHtml(
                ($isEdit && !empty($bell->bell_photo_path))
                    ? 'Stávající fotka existuje. Nahráním nové ji nahradíte. ✨'
                    : 'Fotka není povinná, ale může pomoci. 🖼️'
            ));

        $form->addInteger('bell_weight', Html::el()->setHtml('⚖️ Váha (kg):'))
            ->setRequired('Prosím, vyplňte váhu zvonu (pokud neznáte, zadejte 0).')
            ->setDefaultValue(0)
            ->setHtmlAttribute('class', 'form-control');

        $submitCaption = $isEdit ? '💾 Uložit změny' : '🚀 Přidat zvon';
        $submitClass = 'btn ' . ($isEdit ? 'btn-primary' : 'btn-success') . ' w-100 mt-4';
        $form->addSubmit('save', $submitCaption)
            ->setHtmlAttribute('class', $submitClass);

        $form->addProtection('Platnost bezpečnostního tokenu vypršela. Prosím, odešlete formulář znovu.');

        $form->setDefaults($defaults);

        $form->onSuccess[] = function (Form $form, $values) use ($onSuccess, $isEdit, $bellId, $bell): void {
            $dbData = [
                'bell_name' => $values->bell_name,
                'workshop_Fid' => $values->workshop_Fid,
                'bell_year' => $values->bell_year ?: null,
                'bell_tuning' => $values->bell_tuning ?: null,
                'bell_note' => $values->bell_note ?: null,
                'place_Fid' => $values->place_Fid,
                'bell_extra' => $values->bell_extra ?: null,
                'bell_weight' => $values->bell_weight,
            ];
            $dbOperationSuccess = false;

            $photoUploaded = $values->bell_photo_path instanceof FileUpload && $values->bell_photo_path->isOk() && $values->bell_photo_path->isImage();

            if ($photoUploaded) {
                try {
                    if ($isEdit && !empty($bell->bell_photo_path)) {
                        $this->deleteExistingFile($bellId, 'bell_photo_path');
                    }
                    $newPhotoPath = $this->imageFactory->processImage($values->bell_photo_path, 'bells');

                    $dbData['bell_photo_path'] = $newPhotoPath;

                } catch (\Exception $e) {
                    $form['bell_photo_path']->addError('Bohužel, došlo k chybě při nahrávání fotky. Zkuste to prosím znovu.');
                    return;
                }
            } elseif (!$isEdit) {
                $dbData['bell_photo_path'] = null;
            }

            try {
                $dataToUpdate = $dbData;
                if ($isEdit) {
                    foreach ($dataToUpdate as $key => $value) {
                        if (isset($bell->$key) && $bell->$key === $value) {
                            unset($dataToUpdate[$key]);
                        }
                    }
                }

                if ($isEdit) {
                    if (!empty($dataToUpdate)) {
                        $this->bellRepository->updateBell($bellId, $dataToUpdate);
                    }
                } else {
                    $this->bellRepository->addBell($dbData);
                }

                $dbOperationSuccess = true;

            } catch (Throwable $e) {
                $form->addError('Ach ne! Něco se pokazilo při ukládání zvonu. Zkuste to prosím znovu.');
            }

            if ($dbOperationSuccess) {
                try {
                    $onSuccess($values);
                } catch (AbortException $ab) {
                    throw $ab;
                } catch (Throwable $e) {
                    $form->addError('Zvon byl zpracován, ale došlo k chybě v posledním kroku.');
                }
            }
        };

        return $form;
    }

    private function deleteExistingFile(int $bellId, string $fieldName): void
    {
        $existingBell = $this->bellRepository->getBellById($bellId);
        if ($existingBell && isset($existingBell->$fieldName) && !empty($existingBell->$fieldName)) {
            $filePath = $existingBell->$fieldName;
            if ($this->storageManager->fileExists($filePath)) {
                $this->storageManager->deleteFileOrImage($filePath);
            }
        }
    }
}