<?php

namespace App\UI\Admin\Feast;

use App\Forms\FormFactory;
use App\Forms\ImageFactory;
use App\Forms\StorageManager;
use App\Model\Catholic\FeastRepository;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;

class FeastFormFactory
{
    public function __construct(
        private readonly FormFactory     $formFactory,
        private readonly FeastRepository $facade,
        private readonly ImageFactory    $imageFactory,
        private readonly StorageManager  $storageManager
    ) {}

    public function create(callable $onSuccess, int $feastId = null): Form
    {
        $form = $this->formFactory->create();

        $form->addHidden('feast_id', (string) $feastId);

        $form->addText('saint_feast', Html::el()->setHtml('<i class="bi bi-star"></i> <strong>Saint Feast</strong>:'))
            ->setRequired('Please enter the name of the Feast.');

        $form->addTextArea('detail', Html::el()->setHtml('<i class="bi bi-info-circle"></i> <strong>Detail:</strong>'));

        $row = $form->addRow();

        $row->addCell(6)
            ->addSelect('feast_levelus_Fid', Html::el()->setHtml('<i class="bi bi-layers"></i> <strong>Feast Level</strong>:'),
                $this->facade->getFeastLevels()
            )
            ->setRequired('Please select the Feast level.');

        $row->addCell(6)
            ->addSelect('feast_species_Fid', Html::el()->setHtml('<i class="bi bi-tree"></i> <strong>Feast Species</strong>:'),
                $this->facade->getFeastSpecies()
            )
            ->setRequired('Please select the Feast species.');

        $row = $form->addRow();
        $row->addCell(6)
            ->addText('feast_date', Html::el()->setHtml('<i class="bi bi-calendar2"></i> <strong>Feast Date (MM-DD)</strong>:'))
            ->setRequired('Please enter the date of the Feast.')
            ->addRule(Form::PATTERN, 'Please use the format MM-DD', '\d{2}-\d{2}');

        $row->addCell(6)
            ->addUpload('file_path', Html::el()->setHtml('<i class="bi bi-file-earmark"></i> <strong>File (optional)</strong>:'))
            ->addRule(Form::IMAGE, 'The file must be an image (JPEG, PNG, GIF, or WebP).');

        $form->addSubmit('send', Html::el()->setHtml('<i class="bi bi-send"></i> <strong>' . ($feastId ? 'Update' : 'Submit') . '</strong>'))
            ->setHtmlAttribute('class', 'btn btn-success');

        $form->addProtection();

        $form->onSuccess[] = function (Form $form, ArrayHash $values) use ($onSuccess): void {
            // Retrieve feastId from the form data
            $feastId = (int) $values->feast_id;
            
            // Handle file deletion and processing logic
            if ($feastId) {
                $existingFeast = $this->facade->getFeast($feastId);
                if ($existingFeast && $existingFeast->file_path && isset($values->file_path) && $values->file_path->isOk() && $values->file_path->isImage()) {
                    $this->storageManager->deleteFileOrImage($existingFeast->file_path);
                }
            }
        
            if (isset($values->file_path) && $values->file_path->isOk() && $values->file_path->isImage()) {
                $values->file_path = $this->imageFactory->processImage($values->file_path);
            } else {
                $values->file_path = $feastId && isset($existingFeast) ? $existingFeast->file_path : null;
            }
        
            $valuesArray = (array) $values;
            
            if ($feastId) {
                $this->facade->updateFeast($feastId, ArrayHash::from($valuesArray));
            } else {
                $this->facade->addFeast(ArrayHash::from($valuesArray));
            }
        
            $onSuccess();
        };

        return $form;
    }
}