<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuctionAdjudicated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Los datos de la subasta para el correo.
     */
    public $auction;
    
    /**
     * Los datos del revendedor adjudicado.
     */
    public $reseller;
    
    /**
     * Asunto del correo.
     */
    public $subject;

    /**
     * Create a new message instance.
     */
    public function __construct(array $auction, array $reseller)
    {
        $this->auction = $auction;
        $this->reseller = $reseller;
        $this->subject = "Subasta #{$auction['id']} Adjudicada: {$auction['placa']}";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.auction-adjudicated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
} 