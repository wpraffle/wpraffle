<?php
/**
 * WPRaffle — Lightweight PDF generator for entry lists.
 *
 * Generates raw PDF 1.4 using built-in Helvetica fonts — no external libraries required.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPRaffle_PDF {

    const PW = 595.28;   // A4 width in points
    const PH = 841.89;   // A4 height in points
    const ML = 40;        // Margin left
    const MR = 40;        // Margin right
    const MT = 50;        // Margin top
    const MB = 50;        // Margin bottom
    const RH = 18;        // Row height

    /**
     * Build an entry-list PDF for a closed raffle.
     *
     * @param object $raffle       Raffle DB row.
     * @param array  $tickets      Array of ticket/purchase rows.
     * @param int    $total_digits  Number of digits for zero-padded ticket numbers.
     * @return string  Raw PDF content.
     */
    public static function entry_list( $raffle, $tickets, $total_digits ) {
        $me = new self();

        $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
            'company_name' => get_bloginfo( 'name' ),
        ) );

        $draw_label = $raffle->draw_date
            ? date_i18n( 'jS F Y', strtotime( $raffle->draw_date ) )
            : 'N/A';

        $title    = $raffle->title . ' — Entry List';
        $subtitle = $general['company_name'] . '  |  '
                    . $raffle->sold_tickets . ' entries  |  Draw: ' . $draw_label;

        $headers = array( 'Ticket Number', 'Entrant Name', 'Entry Type', 'Entry Date' );

        $rows = array();
        foreach ( $tickets as $t ) {
            $rows[] = array(
                str_pad( $t->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
                self::anonymise_name( $t->buyer_name ),
                ucfirst( $t->entry_type ),
                $t->purchase_date,
            );
        }

        return $me->build( $title, $subtitle, $headers, $rows, $general['company_name'] );
    }

    /**
     * Build a single-ticket PDF suitable for email attachment.
     *
     * Uses the same raw-PDF primitives as entry_list() but a compact,
     * single-ticket layout (no table). The result is a string that can be
     * passed to PhpMailer::addStringAttachment().
     *
     * @param object $raffle          Raffle DB row.
     * @param array  $ticket_numbers  Array of int ticket numbers for this buyer.
     * @param string $buyer_name      Entrant name.
     * @return string Raw PDF content.
     */
    public static function ticket( $raffle, $ticket_numbers, $buyer_name = '' ) {
        $me = new self();

        $general = wp_parse_args( get_option( 'wpraffle_general_settings', array() ), array(
            'company_name' => get_bloginfo( 'name' ),
        ) );

        $total_digits = strlen( (string) $raffle->total_tickets );
        $draw_label   = $raffle->draw_date
            ? date_i18n( 'jS F Y \a\t g:i a', strtotime( $raffle->draw_date ) )
            : 'To be confirmed';

        $title    = $raffle->title;
        $subtitle = $general['company_name'] . '  |  Official Ticket(s)';

        $y       = self::PH - self::MT;
        $content = '';

        $content .= $me->text( self::ML, $y, $title, 18, true );
        $y      -= 22;
        $content .= $me->text( self::ML, $y, $subtitle, 10, false );
        $y      -= 14;
        $content .= $me->hline( self::ML, $y, self::PW - self::MR, 0.8 );
        $y      -= 24;

        if ( $buyer_name ) {
            $content .= $me->text( self::ML, $y, 'Holder: ' . $buyer_name, 11, false );
            $y      -= 18;
        }
        $content .= $me->text( self::ML, $y, 'Draw: ' . $draw_label, 11, false );
        $y      -= 24;

        // Ticket numbers in a highlighted box.
        $usable = self::PW - self::ML - self::MR;
        $box_h  = 28 + ( count( $ticket_numbers ) * 20 );
        $content .= $me->filled_rect( self::ML, $y - $box_h + 18, $usable, $box_h, 0.94, 0.95, 1.0 );

        $content .= $me->text( self::ML + 8, $y, 'TICKET NUMBER(S)', 9, true );
        $y      -= 18;
        foreach ( $ticket_numbers as $n ) {
            $formatted = str_pad( $n, $total_digits, '0', STR_PAD_LEFT );
            $content .= $me->text( self::ML + 8, $y, $formatted, 14, true );
            $y      -= 20;
        }

        $y      -= 20;
        $content .= $me->text( self::ML, $y, 'Keep this ticket as proof of entry.', 8, false );

        $pages = array( $content );
        return $me->assemble( $pages );
    }

    /* ───────────────────────── internal ───────────────────────── */

    /**
     * Build the full PDF document.
     */
    private static function anonymise_name( $name ) {
        $name = trim( (string) $name );
        if ( $name === '' ) {
            return '-';
        }
        $parts = array_filter( explode( ' ', $name ) );
        $initials = array_map( function ( $part ) {
            return strtoupper( mb_substr( $part, 0, 1 ) );
        }, $parts );
        return implode( '.', $initials );
    }

    private function build( $title, $subtitle, $headers, $rows, $company ) {
        $usable = self::PW - self::ML - self::MR;
        $col_w  = array( $usable * 0.18, $usable * 0.40, $usable * 0.15, $usable * 0.27 );
        $col_x  = array( self::ML );
        for ( $i = 1; $i < 4; $i++ ) {
            $col_x[ $i ] = $col_x[ $i - 1 ] + $col_w[ $i - 1 ];
        }

        $pages   = array();
        $y       = self::PH - self::MT;
        $content = '';

        // ── First page: title block ──
        $content .= $this->text( self::ML, $y, $title, 16, true );
        $y      -= 22;
        $content .= $this->text( self::ML, $y, $subtitle, 9, false );
        $y      -= 12;
        $content .= $this->hline( self::ML, $y, self::PW - self::MR, 0.8 );
        $y      -= 12;

        // Header row
        $content .= $this->header_row( $col_x, $col_w, $headers, $y, $usable );
        $y      -= self::RH;

        // Data rows
        foreach ( $rows as $idx => $row ) {
            // Page break?
            if ( $y - 6 < self::MB + 20 ) {
                $pages[] = $content;
                $content = '';
                $y       = self::PH - self::MT;
                // Repeat header on every page
                $content .= $this->header_row( $col_x, $col_w, $headers, $y, $usable );
                $y      -= self::RH;
            }

            // Alternating row background
            if ( $idx % 2 === 0 ) {
                $content .= $this->filled_rect( self::ML, $y - 5, $usable, self::RH, 0.96, 0.96, 0.97 );
            }

            for ( $i = 0; $i < count( $row ); $i++ ) {
                $content .= $this->text( $col_x[ $i ] + 4, $y + 2, $row[ $i ], 9, false );
            }

            $y -= self::RH;
        }

        if ( $content ) {
            $pages[] = $content;
        }

        // ── Add footer to every page ──
        $total_pages = count( $pages );
        foreach ( $pages as $i => &$pc ) {
            $pn  = $i + 1;
            $fy  = self::MB - 8;
            $pc .= $this->hline( self::ML, $fy, self::PW - self::MR, 0.4 );
            $pc .= $this->text( self::ML, $fy - 13, $company . '  —  Entry List', 7, false );
            $pc .= $this->text_r( self::PW - self::MR, $fy - 13, "Page {$pn} of {$total_pages}", 7, false );
        }
        unset( $pc );

        return $this->assemble( $pages );
    }

    /* ── PDF drawing primitives ── */

    /** Draw text (left-aligned). */
    private function text( $x, $y, $str, $size, $bold ) {
        $f = $bold ? '/F2' : '/F1';
        $s = $this->esc( $str );
        return "BT {$f} {$size} Tf 0.15 0.15 0.15 rg {$x} " . round( $y, 1 ) . " Td ({$s}) Tj ET\n";
    }

    /** Draw text (right-aligned at $x). */
    private function text_r( $x, $y, $str, $size, $bold ) {
        $f = $bold ? '/F2' : '/F1';
        $s = $this->esc( $str );
        return "BT {$f} {$size} Tf 0.5 0.5 0.5 rg {$x} " . round( $y, 1 ) . " Td ({$s}) Tj ET\n";
    }

    /** Horizontal line. */
    private function hline( $x1, $y, $x2, $w ) {
        return "{$w} w 0.8 0.8 0.8 RG {$x1} " . round( $y, 1 ) . " m {$x2} " . round( $y, 1 ) . " S 0.15 0.15 0.15 RG 1 w\n";
    }

    /** Filled rectangle. */
    private function filled_rect( $x, $y, $w, $h, $r, $g, $b ) {
        return sprintf( "%.2f %.2f %.2f rg %.1f %.1f %.1f %.1f re f\n", $r, $g, $b, $x, $y, $w, $h );
    }

    /** Dark header row with white text. */
    private function header_row( $col_x, $col_w, $headers, $y, $usable ) {
        $c  = $this->filled_rect( self::ML, $y - 5, $usable, self::RH, 0.18, 0.18, 0.30 );
        $c .= "1 1 1 rg\n";
        foreach ( $headers as $i => $h ) {
            $s  = $this->esc( $h );
            $tx = $col_x[ $i ] + 4;
            $ty = round( $y + 2, 1 );
            $c .= "BT /F2 9 Tf 1 1 1 rg {$tx} {$ty} Td ({$s}) Tj ET\n";
        }
        // Reset color
        $c .= "0 0 0 rg\n";
        return $c;
    }

    /** Escape a string for PDF literal syntax. */
    private function esc( $str ) {
        if ( function_exists( 'iconv' ) ) {
            $conv = @iconv( 'UTF-8', 'Windows-1252//IGNORE', $str );
            if ( $conv !== false ) {
                $str = $conv;
            }
        }
        return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $str );
    }

    /* ── PDF assembly ── */

    /**
     * Assemble the final PDF document from page content streams.
     */
    private function assemble( $pages ) {
        $objs = array();

        // Obj 1 — Catalog
        $objs[] = '<< /Type /Catalog /Pages 2 0 R >>';
        // Obj 2 — Pages (placeholder)
        $objs[] = '';
        // Obj 3 — Helvetica
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        // Obj 4 — Helvetica-Bold
        $objs[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $page_ids = array();
        foreach ( $pages as $stream ) {
            $page_num   = count( $objs ) + 1;
            $stream_num = $page_num + 1;
            $page_ids[] = $page_num;

            $objs[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                      . self::PW . ' ' . self::PH . '] /Contents '
                      . $stream_num . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>';

            $objs[] = '<< /Length ' . strlen( $stream ) . " >> stream\n" . $stream . 'endstream';
        }

        // Fix Obj 2 — Pages
        $kids     = implode( ' ', array_map( function ( $id ) { return $id . ' 0 R'; }, $page_ids ) );
        $objs[1]  = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count( $pages ) . ' >>';

        // Serialize
        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = array();

        foreach ( $objs as $i => $body ) {
            $num            = $i + 1;
            $offsets[ $num ] = strlen( $pdf );
            $pdf           .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xref_pos = strlen( $pdf );
        $total    = count( $objs ) + 1;
        $pdf     .= "xref\n0 {$total}\n";
        $pdf     .= "0000000000 65535 f \n";
        foreach ( $objs as $i => $_ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i + 1 ] );
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF\n";

        return $pdf;
    }
}
