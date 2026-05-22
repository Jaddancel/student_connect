<?php

namespace App\Faker;

use Faker\Provider\Base;

class FilipinoPersonProvider extends Base
{
    protected static array $maleFirstNames = [
        'Jose', 'Juan', 'Miguel', 'Antonio', 'Carlo', 'Gabriel', 'Rafael', 'Angelo',
        'Jerome', 'Francis', 'Mark', 'John', 'Patrick', 'Christian', 'Daniel', 'Ryan',
        'Vincent', 'Eduardo', 'Manuel', 'Andres', 'Pablo', 'Luis', 'Ricardo', 'Roberto',
        'Ramon', 'Rodrigo', 'Ferdinand', 'Ernesto', 'Alfredo', 'Arturo', 'Bernardo',
        'Ceasar', 'Dante', 'Edgar', 'Felipe', 'Gregorio', 'Herminio', 'Isidro',
        'Jaime', 'Kenneth', 'Leonardo', 'Mariano', 'Nestor', 'Oscar', 'Pedro',
        'Renato', 'Salvador', 'Teodoro', 'Victor', 'Wilfredo', 'Nathan', 'Liam',
        'Elijah', 'Ethan', 'Caleb', 'Adrian', 'Jerome', 'Renzo', 'Tristan', 'Nico',
    ];

    protected static array $femaleFirstNames = [
        'Maria', 'Ana', 'Liza', 'Karen', 'Carla', 'Jessa', 'Joy', 'Precious',
        'Grace', 'Faith', 'Lovely', 'Angel', 'Maricel', 'Rosario', 'Luz',
        'Leonora', 'Maribel', 'Cheryl', 'Rowena', 'Clarissa', 'Mylene', 'Jasmine',
        'Donna', 'Nicole', 'Kathleen', 'Rhea', 'Sheila', 'Cristina', 'Lorraine',
        'Irene', 'Angelica', 'Bianca', 'Camille', 'Denise', 'Erica', 'Francesca',
        'Gina', 'Hazel', 'Irma', 'Jocelyn', 'Kristine', 'Lourdes', 'Melanie',
        'Noreen', 'Olivia', 'Patricia', 'Queenie', 'Regina', 'Sandra', 'Theresa',
        'Ursula', 'Vanessa', 'Wilhelmina', 'Xyra', 'Yvonne', 'Zenaida', 'Chloe',
        'Sofia', 'Isabella', 'Alexa', 'Trisha', 'Janelle', 'Kyla', 'Shaina',
    ];

    protected static array $lastNames = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza',
        'Torres', 'Tomas', 'Andres', 'Castillo', 'Villanueva', 'Navarro', 'Ramos',
        'Flores', 'Gonzales', 'De Leon', 'Aquino', 'Diaz', 'Dela Cruz', 'Lim',
        'Tan', 'Chua', 'Sy', 'Co', 'Uy', 'Dy', 'Go', 'Ong', 'Ang',
        'Aguilar', 'Alcantara', 'Alegre', 'Almeda', 'Alvarez', 'Arroyo',
        'Bacani', 'Baluyot', 'Buenaventura', 'Cabrera', 'Caguioa', 'Calma',
        'Delos Santos', 'Dela Rosa', 'Domingo', 'Espiritu', 'Estrada',
        'Evangelista', 'Fajardo', 'Fernandez', 'Francisco', 'Fuentes',
        'Gutierrez', 'Hernandez', 'Hidalgo', 'Ilagan', 'Jacinto', 'Javier',
        'Lacson', 'Lagunzad', 'Lapuz', 'Laurel', 'Lazaro', 'Lopez',
        'Macapagal', 'Macaraeg', 'Magno', 'Manalo', 'Manipon', 'Manrique',
        'Marasigan', 'Marcos', 'Martin', 'Martinez', 'Medina', 'Miranda',
        'Molina', 'Montemayor', 'Morales', 'Munoz', 'Nepomuceno', 'Nicolas',
        'Ona', 'Ong', 'Ortiz', 'Paguia', 'Palma', 'Paraiso', 'Pascual',
        'Perez', 'Pineda', 'Poblete', 'Ponce', 'Punzalan', 'Quiambao',
        'Quimpo', 'Quintos', 'Rivera', 'Robles', 'Rodriguez', 'Rojas',
        'Roman', 'Roxas', 'Ruiz', 'Salas', 'Salazar', 'Salgado', 'Salinas',
        'San Diego', 'Santiago', 'Sarmiento', 'Serrano', 'Sierra', 'Silva',
        'Soriano', 'Soria', 'Sulit', 'Sy', 'Tayag', 'Tiamzon', 'Tolentino',
        'Uy', 'Valencia', 'Valenzuela', 'Velasco', 'Vera', 'Vergara', 'Villa',
        'Villafuerte', 'Villareal', 'Villarin', 'Villasis', 'Yap', 'Zarate',
    ];

    public function filipinoFirstName(?string $sex = null): string
    {
        if ($sex === 'Male') {
            return static::randomElement(static::$maleFirstNames);
        }

        if ($sex === 'Female') {
            return static::randomElement(static::$femaleFirstNames);
        }

        $pool = array_merge(static::$maleFirstNames, static::$femaleFirstNames);

        return static::randomElement($pool);
    }

    public function filipinoMaleFirstName(): string
    {
        return static::randomElement(static::$maleFirstNames);
    }

    public function filipinoFemaleFirstName(): string
    {
        return static::randomElement(static::$femaleFirstNames);
    }

    public function filipinoLastName(): string
    {
        return static::randomElement(static::$lastNames);
    }
}
