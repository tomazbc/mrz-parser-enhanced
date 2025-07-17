<?php

namespace Tomazbc\MrzParserEnhanced\Parser;

use Tomazbc\MrzParserEnhanced\Contracts\ParserInterface;
use Tomazbc\MrzParserEnhanced\Traits\CountryMapper;
use Tomazbc\MrzParserEnhanced\Traits\DateFormatter;
use Tomazbc\MrzParserEnhanced\Traits\GenderMapper;

class TravelDocument1MrzParser implements ParserInterface
{
    use DateFormatter;
    use GenderMapper;
    use CountryMapper;

    protected $text;

    protected $firstLine;

    protected $secondLine;

    protected $thirdLine;

    protected $nameString;

    /**
     * Example String
     * Source: https://www.icao.int/publications/Documents/9303_p5_cons_en.pdf
     *
     * I<UTOD231458907<<<<<<<<<<<<<<<
     * 7408122F1204159UTO<<<<<<<<<<<6
     * ERIKSSON<<ANNA<MARIA<<<<<<<<<<
     */

    /**
     * Detect MRZ country code format (alpha-2 vs alpha-3)
     * Alpha-2: I<CC< (position 4 = '<')
     * Alpha-3: I<CCC (position 4 = letter/digit)
     */
    protected function detectCountryCodeFormat(): string
    {
        return (substr($this->firstLine, 4, 1) === '<') ? 'alpha-2' : 'alpha-3';
    }
    protected function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Set Name String
     */
    protected function setNameString(): self
    {
        $this->nameString = explode('<<', $this->thirdLine);

        return $this;
    }

    /**
     * Extract information
     */
    protected function extract(): self
    {
        $text = explode("\n", $this->text);
        $this->firstLine = $text[0] ?? null;
        $this->secondLine = $text[1] ?? null;
        $this->thirdLine = $text[2] ?? null;
        $this->setNameString();

        return $this;
    }

    /**
     * Get Type beased on first two string
     *
     * Type, This is at the discretion of the issuing state or authority,
     * but 1–2 should be AC for Crew Member Certificates and V is not allowed as 2nd character.
     * ID or I< are typically used for nationally issued ID cards and IP for passport cards.
     */
    protected function getType()
    {
        $firstTwoCharacter = substr($this->firstLine, 0, 2);

        return match ($firstTwoCharacter) {
            'AC' => 'Crew Member Certificates',
            'I<' => 'National ID',
            'IP' => 'Passport',
            default => "Travel Document (TD1)"
        };
    }

    /**
     * Get Document Number
     * 6–14	alpha+num+<	Document number
     */
    protected function getCardNo(): ?string
    {
        $cardNo = substr($this->firstLine, 5, 9);
        $cardNo = chop($cardNo, "<"); // remove extra '<' from card no

        return $cardNo;
    }

    /**
     * Get Document Issuer
     * Handles both alpha-2 (I<CC<) and alpha-3 (I<CCC) formats
     */
    protected function getIssuer(): ?string
    {
        if ($this->detectCountryCodeFormat() === 'alpha-2') {
            // Alpha-2: I<CC< - extract 2 characters
            $issuer = substr($this->firstLine, 2, 2);
        } else {
            // Alpha-3: I<CCC - extract 3 characters
            $issuer = substr($this->firstLine, 2, 3);
        }

        return $this->mapCountry($issuer);
    }

    /**
     * Get Date of Expiry
     * Second row 9-14	character: (YYMMDD)
     */
    protected function getDateOfExpiry(): ?string
    {
        $date = substr($this->secondLine, 8, 6);

        return $date ? $this->formatDate($date) : null;
    }

    /**
     * Get Date of Birth
     * Second row 1-6	character: (YYMMDD)
     */
    protected function getDateOfBirth(): ?string
    {
        $date = substr($this->secondLine, 0, 6);

        return $date ? $this->formatDate($date) : null;
    }

    /**
     * Get First Name from Name String
     *
     * <<ANNA<MARIA<<<<<<<<<<
     */
    protected function getFirstName(): ?string
    {
        return isset($this->nameString[1]) ? str_replace("<", " ", $this->nameString[1]) : null;
    }

    /**
     * Get Last Name from Name String
     */
    protected function getLastName(): ?string
    {
        return $this->nameString[0] ?? null;
    }

    /**
     * Get Gender
     * Position 8, M/F/<
     *
     */
    protected function getGender(): ?string
    {
        return $this->mapGender(substr($this->secondLine, 7, 1));
    }

    /**
     * Get Nationality
     */
    protected function getNationality(): ?string
    {
        $code = chop(substr($this->secondLine, 15, 3), "<");

        return $this->mapCountry($code);
    }

    /**
     * Get Output from MRZ
     *
     * @return array
     */
    protected function get(): array
    {
        return [
            'type' => $this->getType(),
            'card_no' => $this->getCardNo(),
            'issuer' => $this->getIssuer(),
            'date_of_expiry' => $this->getDateOfExpiry(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'date_of_birth' => $this->getDateOfBirth(),
            'gender' => $this->getGender(),
            'nationality' => $this->getNationality(),
        ];
    }

    /**
     * Parse MRZ to Json Data
     *
     * @param string $text
     * @return null|array
     */
    public function parse(string $text): ?array
    {
        return $this
            ->setText($text)
            ->extract()
            ->get();
    }
}
