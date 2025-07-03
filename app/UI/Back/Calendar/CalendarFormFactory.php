<?php

namespace App\UI\Back\Calendar;

use App\Forms\FileFactory;
use App\Forms\FormFactory;
use App\Forms\ImageFactory;
use App\Forms\StorageManager;
use DateTime;
use Nette\Application\UI\Form;
use App\Model\Calendar\CalendarFacade;
use App\Model\Calendar\CalendarRepository;
use Nette\Security\User; // 1. Import the User service
use Nette\Utils\ArrayHash;
use Nette\Http\FileUpload;
use DateTimeInterface;

readonly class CalendarFormFactory
{
    public function __construct(
        private FormFactory    $formFactory,
        private StorageManager $storageManager,
        private ImageFactory   $imageFactory,
        private FileFactory    $fileFactory,
        private CalendarFacade $facade,
        private CalendarRepository $calendarRepository,
        private User $user // 2. Inject the User service via constructor
    ) {}

    /**
     * Creates the Calendar form.
     *
     * @param callable $onSuccess Callback to be executed after successful form submission and processing.
     * @param int|null $calendarId The ID of the event being edited, or null for a new event.
     * @param array|null $defaultValues Optional default values to pre-fill the form (e.g., from database).
     * @return Form The configured form instance.
     */
    public function create(callable $onSuccess, ?int $calendarId = null, ?array $defaultValues = null): Form
    {
        $form = $this->formFactory->create();
        $form->addHidden('id', $calendarId);

        // --- Default Values and Formatting for HTML Input (Y-m-d\TH:i) ---
        // Initialize $preparedDefaultValues to avoid modifying the original $defaultValues directly if it came from outside
        $preparedDefaultValues = $defaultValues ?? [];

        // Prepare date_start
        if (isset($preparedDefaultValues['date_start']) && !empty($preparedDefaultValues['date_start'])) {
            // Editing existing event with a start date: format it for the HTML input
            $dateStartValue = $preparedDefaultValues['date_start'];
            if ($dateStartValue instanceof DateTimeInterface) {
                $dateStartValue = $dateStartValue->format('Y-m-d H:i:s');
            }
            // Check if it's a valid date string before creating DateTime
            if (is_string($dateStartValue) && strtotime($dateStartValue) !== false) {
                $preparedDefaultValues['date_start'] = (new DateTime($dateStartValue))->format('Y-m-d\TH:i');
            } else {
                // Fallback if database value is not a valid date string somehow
                $preparedDefaultValues['date_start'] = (new DateTime())->format('Y-m-d\TH:i');
            }
        } else {
            // New event or no start date provided: default to current time for the HTML input
            $preparedDefaultValues['date_start'] = (new DateTime())->format('Y-m-d\TH:i');
        }

        // Prepare date_end
        if (isset($preparedDefaultValues['date_end']) && !empty($preparedDefaultValues['date_end'])) {
            // Editing existing event with an end date: format it for the HTML input
            $dateEndValue = $preparedDefaultValues['date_end'];
            if ($dateEndValue instanceof DateTimeInterface) {
                $dateEndValue = $dateEndValue->format('Y-m-d H:i:s');
            }
            // Check if it's a valid date string before creating DateTime
            if (is_string($dateEndValue) && strtotime($dateEndValue) !== false) {
                $preparedDefaultValues['date_end'] = (new DateTime($dateEndValue))->format('Y-m-d\TH:i');
            } else {
                // Fallback or leave empty if database value is not valid
                $preparedDefaultValues['date_end'] = null; // Or ''
            }
        } else {
            // New event or no end date provided: leave it empty for the HTML input
            $preparedDefaultValues['date_end'] = null; // Or ''
        }
        // --- End Default Values Preparation ---


        // Fetch event types and places
        $eventTypes = $this->calendarRepository->getCalNote();
        $eventPlaces = $this->calendarRepository->getCalPlace();

        // --- Add Form Controls ---
        // Using the row/cell helpers from your setup
        $row = $form->addRow();
        $row->addCell(4)
            ->addSelect('Fid_note', 'Event Type:', $eventTypes)
            ->setRequired('Please select an event type.')
            ->setPrompt('Select event type')
            ->setDefaultValue($preparedDefaultValues['Fid_note'] ?? null);

        $row->addCell(4)
            ->addText('date_start', 'Start Time:')
            ->setHtmlAttribute('type', 'datetime-local')
            ->setRequired('Please enter the start time.')
            ->setDefaultValue($preparedDefaultValues['date_start']);

        $row->addCell(4)
            ->addText('date_end', 'End Time:')
            ->setHtmlAttribute('type', 'datetime-local')
            ->setDefaultValue($preparedDefaultValues['date_end']);

        $row = $form->addRow();
        $row->addCell(12)
            ->addText('title', 'Title:')
            ->addRule(Form::MAX_LENGTH, 'The title must be at most %d characters long', 100)
            ->setDefaultValue($preparedDefaultValues['title'] ?? null);

        $row = $form->addRow();
        $row->addCell(12)
            ->addText('note', 'Note:')
            ->addRule(Form::MAX_LENGTH, 'The note must be at most %d characters long', 100) // Corrected error message
            ->setDefaultValue($preparedDefaultValues['note'] ?? null);

        $row = $form->addRow();
        $row->addCell(4)
            ->addSelect('Fid_place', 'Event Place:', $eventPlaces)
            ->setRequired('Please select an event place.')
            ->setPrompt('Select event place')
            ->setDefaultValue($preparedDefaultValues['Fid_place'] ?? null);

        $row->addCell(4)
            ->addText('place', 'Location (if not in the event place):')
            ->setDefaultValue($preparedDefaultValues['place'] ?? null);

        $row->addCell(4)
            ->addText('person', 'celebrant or přednášející')
            ->setDefaultValue($preparedDefaultValues['person'] ?? null);

        $row = $form->addRow();
        $row->addCell(12)
            ->addTextArea('content', 'Content:')
            ->setHtmlAttribute('rows', 5)
            ->setDefaultValue($preparedDefaultValues['content'] ?? null);

        $row = $form->addRow();
        $row->addCell(3)
            ->addUpload('photo', 'Photo (optional):')
            ->addRule(Form::MimeType, 'The photo must be JPEG, PNG, GIF, or WebP.', [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ]);

        $row->addCell(3)
            ->addUpload('filePdf', 'PDF or Excel file (optional):')
            ->addRule(Form::MimeType, 'The file must be a PDF or an Excel file.', [
                'application/pdf',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]);

        $row->addCell(6)
            ->addText('web', 'Web link (optional):')
            ->setDefaultValue($preparedDefaultValues['web'] ?? null);

        $form->addSubmit('send', 'Submit')->setHtmlAttribute('class', 'my-3 btn btn-primary');

        $form->addProtection();
        // --- End Add Form Controls ---


        // --- onSuccess Callback (Processing Submitted Values) ---
        $form->onSuccess[] = function (Form $form, ArrayHash $values) use ($onSuccess, $calendarId): void {

            // --- Date Conversion ---
            // Convert Y-m-d\TH:i from HTML input back to Y-m-d H:i:s for MariaDB DATETIME
            try {
                $startDate = new DateTime($values->date_start);
                $values->date_start = $startDate->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                $form['date_start']->addError('Invalid start date format.');
                return;
            }

            // Handle date_end: If empty, set it to date_start + 1 hour, otherwise convert it
            if (empty($values->date_end)) {
                // Use the DateTime object created from date_start
                $values->date_end = $startDate->modify('+1 hour')->format('Y-m-d H:i:s');
            } else {
                // Convert provided date_end
                try {
                    $endDate = new DateTime($values->date_end);
                    $values->date_end = $endDate->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $form['date_end']->addError('Invalid end date format.');
                    return;
                }
            }
            // --- End Date Conversion ---


            // --- Convert Empty Strings to NULL for Optional Text Fields ---
            // List the fields that should be NULL in the DB if left empty in the form.
            $optionalTextFields = ['title', 'note', 'place', 'person', 'content', 'web'];

            foreach ($optionalTextFields as $fieldName) {
                // Check if the value exists in submitted data, is a string, and is empty
                if (isset($values->$fieldName) && is_string($values->$fieldName) && $values->$fieldName === '') {
                    $values->$fieldName = null; // Convert empty string to NULL
                }
            }
            // --- End Conversion to NULL ---


            // --- File Upload Handling ---
            if ($values->photo instanceof FileUpload && $values->photo->isOk() && $values->photo->isImage()) {
                if ($calendarId) {
                    $this->deleteExistingFile($calendarId, 'photo');
                }
                $values->photo = $this->imageFactory->processImage($values->photo);
            } else {
                // If no new file uploaded, remove from values.
                // IMPORTANT: If you need to *remove* an existing file when editing,
                // you would need a separate checkbox or mechanism for that.
                // This logic only handles uploading a *new* file or keeping the old one if none is uploaded.
                unset($values->photo);
            }

            if ($values->filePdf instanceof FileUpload && $values->filePdf->isOk()) {
                if ($calendarId) {
                    $this->deleteExistingFile($calendarId, 'filePdf');
                }
                $values->filePdf = $this->fileFactory->processFile($values->filePdf);
            } else {
                // If no new file uploaded, remove from values.
                unset($values->filePdf);
            }
            // --- End File Upload Handling ---


            // --- Save Data ---
            if ($calendarId) {
                // Update existing event
                $this->facade->updateEvent($values, $calendarId);
            } else {
                // Add new event
                unset($values->id);
                // Add any default values for new events not from the form
                $values->is_visible = $values->is_visible ?? 1; // Example: set default visibility if not on form

                // 3. Use the injected user service
                // Ensure the user is logged in before trying to get the ID
                if ($this->user->isLoggedIn()) {
                    $values->add_by_user_Fid = $this->user->getId();
                } else {
                    // Handle cases where the user might not be logged in, if necessary
                    // e.g., throw an exception, set a default/null value, or add a form error.
                    // For now, let's assume being logged in is required to add an event.
                    $form->addError('You must be logged in to add an event.');
                    return; // Stop processing if user is not logged in
                }


                $this->facade->addEvent($values);
            }
            // --- End Save Data ---

            // Call the user-provided success callback
            $onSuccess();
        };
        // --- End onSuccess Callback ---

        return $form;
    }

    /**
     * Deletes an existing file or image associated with a calendar event.
     *
     * @param int $calendarId The ID of the calendar event.
     * @param string $fieldName The form field name ('photo' or 'filePdf').
     */
    private function deleteExistingFile(int $calendarId, string $fieldName): void
    {
        // Use the repository to get the event data to find the file path
        $existingEvent = $this->calendarRepository->getEventById($calendarId);
        // Assuming the repository returns an object or array that can be accessed like an array
        if ($existingEvent && isset($existingEvent[$fieldName]) && !empty($existingEvent[$fieldName])) {
            $filePath = $existingEvent[$fieldName];
            // Check if the file exists on the filesystem before attempting deletion
            if ($this->storageManager->fileExists($filePath)) { // Assuming StorageManager has a fileExists method
                $this->storageManager->deleteFileOrImage($filePath);
            }
        }
    }
}