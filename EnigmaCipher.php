<?php

/**
 * Enigma Machine Simulation
 *
 * This class simulates the original Enigma cipher machine with rotors, reflectors, 
 * and plugboard, allowing encryption and decryption of messages. The machine supports
 * configurable rotors, reflector, plugboard wiring, and ring settings.
 *
 * @category  Cryptography
 * @package   EnigmaCipher
 * @author    Ramazan Çetinkaya <ramazancetinkayadev@proton.me>
 * @license   MIT License
 * @version   1.0.0
 * @link      https://github.com/ramazancetinkaya/enigma-cipher
 */
class Enigma
{
    private array $rotors;
    private EnigmaReflector $reflector;
    private Plugboard $plugboard;

    /**
     * Enigma constructor.
     *
     * Initializes the Enigma machine with the provided components.
     *
     * @param array           $rotors    Array of Rotor objects.
     * @param EnigmaReflector $reflector Reflector object for reflecting characters.
     * @param Plugboard       $plugboard Plugboard object for letter substitution.
     *
     * @throws InvalidArgumentException If the components are invalid.
     */
    public function __construct(array $rotors, EnigmaReflector $reflector, Plugboard $plugboard)
    {
        $this->validateComponents($rotors, $reflector, $plugboard);
        $this->rotors = $rotors;
        $this->reflector = $reflector;
        $this->plugboard = $plugboard;
    }

    /**
     * Encrypt or decrypt a message.
     *
     * Processes the input message character by character. Spaces are preserved,
     * but non-alphabetic characters will cause an exception.
     *
     * @param string $message The input message (uppercased alphabetic characters and spaces).
     * @return string The processed (encrypted or decrypted) message.
     *
     * @throws InvalidArgumentException If the message contains invalid characters.
     */
    public function processMessage(string $message): string
    {
        $output = '';
        foreach (str_split(strtoupper($message)) as $character) {
            if ($character === ' ') {
                $output .= ' '; // Preserve spaces in the message
                continue;
            }
            if (!ctype_alpha($character)) {
                throw new InvalidArgumentException("Message contains invalid characters. Only uppercase A-Z and spaces are allowed.");
            }
            // Save the initial state of the rotors before processing
            $this->saveRotorStates();
            $output .= $this->processCharacter($character);
            // Restore the rotor states after processing
            $this->restoreRotorStates();
        }
        return $output;
    }

    /**
     * Process a single character through the Enigma machine.
     *
     * @param string $character The character to process (must be uppercase).
     * @return string The processed character after passing through the machine.
     */
    private function processCharacter(string $character): string
    {
        // Step 1: Pass the character through the plugboard
        $character = $this->plugboard->swap($character);

        // Step 2: Pass the character through each rotor in forward direction
        foreach ($this->rotors as $rotor) {
            $character = $rotor->encodeForward($character);
        }

        // Step 3: Reflect the character using the reflector
        $character = $this->reflector->reflect($character);

        // Step 4: Pass the character back through each rotor in reverse direction
        foreach (array_reverse($this->rotors) as $rotor) {
            $character = $rotor->encodeReverse($character);
        }

        // Step 5: Swap the character again through the plugboard
        $character = $this->plugboard->swap($character);

        // Step 6: Rotate the rotors after each key press
        $this->rotateRotors();

        return $character;
    }

    /**
     * Save the current state of each rotor.
     */
    private function saveRotorStates(): void
    {
        foreach ($this->rotors as $rotor) {
            $rotor->saveState();
        }
    }

    /**
     * Restore the saved state of each rotor.
     */
    private function restoreRotorStates(): void
    {
        foreach ($this->rotors as $rotor) {
            $rotor->restoreState();
        }
    }

    /**
     * Rotates the rotors based on the Enigma stepping mechanism.
     *
     * The rightmost rotor rotates with each key press, and subsequent rotors rotate
     * if their notch positions are reached.
     */
    private function rotateRotors(): void
    {
        $rotateNext = true;
        foreach ($this->rotors as $rotor) {
            if ($rotateNext) {
                $rotateNext = $rotor->rotate();
            } else {
                break;
            }
        }
    }

    /**
     * Validates the components of the Enigma machine.
     *
     * Ensures that the machine has at least 3 rotors, a valid reflector, and a plugboard.
     *
     * @param array           $rotors    Array of Rotor objects.
     * @param EnigmaReflector $reflector Reflector object.
     * @param Plugboard       $plugboard Plugboard object.
     *
     * @throws InvalidArgumentException If any component is invalid.
     */
    private function validateComponents(array $rotors, EnigmaReflector $reflector, Plugboard $plugboard): void
    {
        if (count($rotors) < 3) {
            throw new InvalidArgumentException('At least 3 rotors are required.');
        }
        if (!$reflector instanceof EnigmaReflector) {
            throw new InvalidArgumentException('Invalid reflector provided.');
        }
        if (!$plugboard instanceof Plugboard) {
            throw new InvalidArgumentException('Invalid plugboard provided.');
        }
    }
}

/**
 * Rotor Class
 * 
 * Simulates an Enigma rotor, responsible for forward and reverse character encoding, 
 * and rotating based on notch positions.
 */
class Rotor
{
    private string $wiring;
    private string $reverseWiring;
    private int $position;
    private int $ringSetting;
    private int $notch;
    private int $savedPosition;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Rotor constructor.
     *
     * Initializes a rotor with the specified wiring, notch position, ring setting, and initial position.
     *
     * @param string $wiring       A string representing the rotor wiring (permutes A-Z).
     * @param int    $notch        The position where the rotor triggers the next rotor's rotation.
     * @param int    $ringSetting  The ring setting offset for the rotor (default is 0).
     * @param int    $position     The initial position of the rotor (default is 0).
     */
    public function __construct(string $wiring, int $notch, int $ringSetting = 0, int $position = 0)
    {
        $this->wiring = $wiring;
        $this->reverseWiring = $this->generateReverseWiring($wiring);
        $this->notch = $notch;
        $this->ringSetting = $ringSetting;
        $this->position = $position;
    }

    /**
     * Forward encoding through the rotor.
     *
     * Encodes a character as it passes through the rotor in the forward direction.
     *
     * @param string $char The character to encode.
     * @return string The encoded character.
     */
    public function encodeForward(string $char): string
    {
        $index = (strpos(self::ALPHABET, $char) + $this->position - $this->ringSetting + 26) % 26;
        $encodedChar = $this->wiring[$index];
        $outputIndex = (strpos(self::ALPHABET, $encodedChar) - $this->position + $this->ringSetting + 26) % 26;
        return self::ALPHABET[$outputIndex];
    }

    /**
     * Reverse encoding through the rotor.
     *
     * Encodes a character as it passes through the rotor in the reverse direction.
     *
     * @param string $char The character to encode in reverse.
     * @return string The reverse encoded character.
     */
    public function encodeReverse(string $char): string
    {
        $index = (strpos(self::ALPHABET, $char) + $this->position - $this->ringSetting + 26) % 26;
        $encodedChar = $this->reverseWiring[$index];
        $outputIndex = (strpos(self::ALPHABET, $encodedChar) - $this->position + $this->ringSetting + 26) % 26;
        return self::ALPHABET[$outputIndex];
    }

    /**
     * Rotate the rotor by one position.
     *
     * @return bool Whether the next rotor should rotate (true if at the notch position).
     */
    public function rotate(): bool
    {
        $this->position = ($this->position + 1) % 26;
        return $this->position === $this->notch;
    }

    /**
     * Saves the current state of the rotor.
     */
    public function saveState(): void
    {
        $this->savedPosition = $this->position;
    }

    /**
     * Restores the saved state of the rotor.
     */
    public function restoreState(): void
    {
        $this->position = $this->savedPosition;
    }

    /**
     * Generates reverse wiring based on the forward wiring.
     *
     * @param string $wiring The forward wiring.
     * @return string The reverse wiring.
     */
    private function generateReverseWiring(string $wiring): string
    {
        $reverse = array_fill(0, 26, '');
        foreach (str_split(self::ALPHABET) as $i => $char) {
            $encodedChar = $wiring[$i];
            $reverse[strpos(self::ALPHABET, $encodedChar)] = $char;
        }
        return implode('', $reverse);
    }
}

/**
 * Reflector Class
 *
 * Simulates the reflector in the Enigma machine, responsible for reflecting characters 
 * back through the rotors after passing forward.
 */
class EnigmaReflector
{
    private string $wiring;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * EnigmaReflector constructor.
     *
     * @param string $wiring The reflector wiring (permutes A-Z).
     */
    public function __construct(string $wiring)
    {
        $this->wiring = $wiring;
    }

    /**
     * Reflect a character using the reflector wiring.
     *
     * @param string $char Character to reflect.
     * @return string Reflected character.
     */
    public function reflect(string $char): string
    {
        return $this->wiring[strpos(self::ALPHABET, $char)] ?? $char;
    }
}

/**
 * Plugboard Class
 * 
 * Simulates the plugboard of the Enigma machine, where pairs of letters are swapped 
 * before and after passing through the rotors.
 */
class Plugboard
{
    private array $wiring;

    /**
     * Plugboard constructor.
     *
     * Initializes the plugboard wiring.
     *
     * @param array $wiring Associative array where 'A' => 'B' swaps A and B.
     */
    public function __construct(array $wiring = [])
    {
        $this->wiring = $wiring;
    }

    /**
     * Swap characters through the plugboard wiring.
     *
     * @param string $char Character to swap.
     * @return string Swapped character.
     */
    public function swap(string $char): string
    {
        return $this->wiring[$char] ?? $char;
    }
}
