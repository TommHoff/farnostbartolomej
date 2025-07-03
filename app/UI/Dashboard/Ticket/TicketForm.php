<?php

declare(strict_types=1);

namespace App\UI\Dashboard\Ticket;

use App\Forms\FormFactory;
use App\Forms\ImageFactory;
use App\Model\Develepment\TicketRepository;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nette\Security\User;
use Nette\Utils\Html;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException; // Import AbortException
use Nette\Utils\DateTime;
use Tracy\Debugger;
use Tracy\ILogger;
use Exception;
use Throwable;

class TicketForm
{
    private string $adminEmail = 'tomas@hoffmann.cz';

    public function __construct(
        private readonly FormFactory      $formFactory,
        private readonly TicketRepository $ticketRepository,
        private readonly User             $user,
        private readonly ImageFactory     $imageFactory,
        private readonly Mailer           $mailer
    ) {
    }

    public function create(?int $ticketId, callable $onSuccess): Form
    {
        $form = $this->formFactory->create();
        $isEdit = $ticketId !== null;
        $defaults = [];
        $ticket = null;

        if ($isEdit) {
            $ticket = $this->ticketRepository->getTicket($ticketId);
            if ($ticket) {
                $defaults['description'] = $ticket->description;
            } else {
                throw new BadRequestException('Whoops! Ticket not found. 🤷‍♀️');
            }
        }

        $form->addTextArea('description', Html::el()->setHtml('<i class="bi bi-card-text me-2"></i>Description:'))
            ->setRequired('Please tell us what\'s happening!')
            ->setHtmlAttribute('rows', 8)
            ->setHtmlAttribute('placeholder', 'Please provide as much detail as possible...')
            ->setHtmlAttribute('class', 'form-control');

        $uploadLabel = Html::el()->setHtml('<i class="bi bi-image me-2"></i>Screenshot / Photo (Optional):');
        $form->addUpload('photo_path', $uploadLabel)
            ->setNullable()
            ->addRule(Form::IMAGE, 'Oops! Only JPEG, PNG, GIF, or WebP images are allowed.')
            ->addRule(Form::MAX_FILE_SIZE, 'Hold on! The image is too large (max 5MB).', 5 * 1024 * 1024)
            ->setHtmlAttribute('class', 'form-control')
            ->setOption('description', Html::el('small')->class('form-text text-muted')->setHtml(
                ($isEdit && $ticket?->photo_path)
                    ? 'Current photo exists. Uploading a new one will replace it. ✨'
                    : 'A picture is worth a thousand words! Upload if it helps explain. 🖼️'
            ));

        $submitCaption = $isEdit ? '💾 Save Changes' : '🚀 Send Ticket';
        $submitClass = 'btn ' . ($isEdit ? 'btn-primary' : 'btn-success') . ' w-100 mt-4';
        $form->addSubmit('send', $submitCaption)
            ->setHtmlAttribute('class', $submitClass);

        $form->addProtection('Security token expired, please submit the form again.');

        $form->setDefaults($defaults);

        $form->onSuccess[] = function (Form $form, $values) use ($onSuccess, $ticketId, $isEdit, $ticket): void {
            $dbData = [
                'description' => $values->description,
            ];
            $dbOperationSuccess = false;
            $newPhotoPath = null;

            $photoUploaded = $values->photo_path instanceof FileUpload && $values->photo_path->isOk() && $values->photo_path->isImage();

            if ($photoUploaded) {
                try {
                    if ($isEdit && $ticket?->photo_path) {
                        $this->imageFactory->deleteImage($ticket->photo_path);
                    }
                    $newPhotoPath = $this->imageFactory->processImage($values->photo_path);
                    $dbData['photo_path'] = $newPhotoPath;
                } catch (Exception $e) {
                    Debugger::log('Image processing failed: ' . $e->getMessage(), ILogger::ERROR);
                    $form['photo_path']->addError('Sorry, there was an error uploading the image. Please try again.');
                    return;
                }
            } elseif (!$isEdit && !$photoUploaded) {
                $dbData['photo_path'] = null;
            }

            try {
                if ($isEdit) {
                    if (!empty($dbData)) {
                        $this->ticketRepository->updateTicket($ticketId, $dbData);
                    }
                } else {
                    $dbData['user_Fid'] = $this->user->getId();
                    $dbData['receive_ticket'] = new DateTime();
                    $dbData['reply'] = null;
                    $dbData['is_done'] = 0;
                    $dbData['finish_ticket'] = null;

                    $this->ticketRepository->addTicket($dbData);

                    try {
                        $mail = new Message();
                        $mail->setFrom('salve@farnostbartolomej.cz', 'UNI - farnost')
                            ->addTo($this->adminEmail)
                            ->setSubject('🚨 Nový požadavek na podporu byl odeslán!')
                            ->setHtmlBody(
                                "<p>Dobrý den! 👋</p>" .
                                "<p>Nový ticket podpory byl právě odeslán uživatelem s ID: <strong>{$dbData['user_Fid']}</strong> a čeká na vyřízení.</p>" .
                                "<p><strong>Popis problému:</strong></p>" .
                                "<blockquote style='border-left: 4px solid #ccc; padding-left: 1em; margin-left: 1em;'>" .
                                nl2br(htmlspecialchars($values->description)) .
                                "</blockquote>" .
                                ($newPhotoPath ? "<p>Byl nahrán obrázek.</p>" : "") .
                                "<p>Podrobnosti můžete zkontrolovat v administraci:</p>" .
                                "<p><a href='" . $form->getPresenter()->link('//:Dashboard:Ticket:default') . "' style='display: inline-block; padding: 10px 15px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>🚀 Otevřít administraci ticketů</a></p>" .
                                "<hr>" .
                                "<p><small>Tato zpráva byla generována automaticky. Neodpovídejte na ni, prosím.</small> 🙏</p>"
                            );
                        $this->mailer->send($mail);
                    } catch (Throwable $emailEx) {
                        Debugger::log('Failed to send NEW ticket notification email: ' . $emailEx->getMessage(), ILogger::WARNING);
                    }
                }

                $dbOperationSuccess = true;

            } catch (Throwable $e) {
                Debugger::log('Ticket form DB operation failed: ' . $e->getMessage(), ILogger::ERROR);
                Debugger::log($e, ILogger::EXCEPTION);
                $form->addError('Oh no! Something went wrong while saving the ticket data. Please try again.');
            }

            if ($dbOperationSuccess) {
                try {
                    $onSuccess($values);
                } catch (AbortException $ab) {
                    throw $ab; // Let Nette handle the redirect/abort signal
                } catch (Throwable $e) {
                    // Catch other potential errors in the presenter's callback
                    Debugger::log('Error occurred within the presenter\'s onSuccess callback: ' . $e->getMessage(), ILogger::ERROR);
                    Debugger::log($e, ILogger::EXCEPTION);
                    // Add error, but DB op succeeded
                    $form->addError('Ticket processed, but a final step failed. Please check the list or contact support.');
                }
            }
        };

        return $form;
    }
}