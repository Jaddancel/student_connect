<?php

namespace App\Helpers;

class OrganizationLogoHelper
{
    public static function map(): array
    {
        $basePath = 'images/organizations';

        return [
            'Buklod-Lahi' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/BUKLOD LAHI TAU.JPG"),
            'Ecological and Solid Waste Management Society' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/ESWM TAU.JPG"),
            'TAU Bulalayaw' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/TAU BULALAYAW.JPG"),
            'Mulat TAU Deabte Society' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/TAU DEBATE SOCIETY.jpg"),
            'Ranchers\' Club Philippines - TAU Chapter' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/RANCHERS CLUB TAU CHAPTER.png"),
            'Rodeo Club' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/TAU RODEO CLUB.jpg"),
            'Veterinary Student Achievers\' Society' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/VSAS.jpg"),
            'Philippine Consortium for Science, Mathematics, and Technology' => self::path("{$basePath}/SOCIO-CIVIC CATEGORY/PCSMT.png"),
            "Campus Mover's For Christ" => self::path("{$basePath}/RELIGIOUS CATEGORY/CAMPUS MOVERS FOR CHRIST.jpg"),
            'Christian Brotherhood International-TAU Chapter' => self::path("{$basePath}/RELIGIOUS CATEGORY/CBI INTERNATIONAL.png"),
            'Christian Youth for Nation' => self::path("{$basePath}/RELIGIOUS CATEGORY/christian youth for nation.png"),
            'Latter-Day Saint Student Association' => self::path("{$basePath}/RELIGIOUS CATEGORY/TAU LATTER DAY SAINTS STUDENT ASSOC.JPG"),
            'Student Catholic Action of the Philippines-TAU Unit' => self::path("{$basePath}/RELIGIOUS CATEGORY/STUDENT CATHOLIC ACTION OF THE PHILIPPINES TAU UNIT.JPG"),
            'Alpha Phi Omega' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/ALPHA PHI OMEGA.png"),
            'Alpha Kappa RHO' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/Alpha_Kappa_Rho_.png"),
            'TAU Gamma Phi/Sigma' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/TAU GAMMA PHI SIGMA.png"),
            'Gamma Sigma Scorpions (Vermilliom Chapter)' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/GAMMA SIGMA SCORPIONS VERMILLION CHAPTER.jpg"),
            'United Ilocandia' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/UNITED ILOVANDIA.jpg"),
            'Venerable Knight Veterinarians/Venerable Lady Veterinarians' => self::path("{$basePath}/FRATERNITIES AND SORORITIES/VENERABLE KNIGHT VET.jpg"),
            'LS - Agriculture and Homemaking Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS AGRI AND HOME MAKING CLUB.JPG"),
            'LS Math Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS MATH CLUB.JPG"),
            'LS - Arts Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS ART CLUB.JPG"),
            'LS Rondalla Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS RONDALLA CLUB.JPG"),
            'LS Boy Scout of the Philippines' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS BSP CLUB.JPG"),
            'LS Science Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS SCI CLUB.JPG"),
            'LS Social Science Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS SOCIAL SCIENCE CLUB.JPG"),
            'LS - Filipino Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS FILIPINO CLUB.jpg"),
            'LS Speech and Debate Society' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS SPEECH AND DEBATE CLUB.JPG"),
            'LS Dance' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS DANCE CLUB.JPG"),
            'LS Sports Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS SPORTS CLUB.JPG"),
            'LS - Glee Club' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS GLEE CLUB.JPG"),
            'LS Girl Scout of the Philippines' => self::path("{$basePath}/SPECIAL INTEREST CATEGORY/LS GSP CLUB.JPG"),
            'Golden Harvest' => self::path("{$basePath}/university sanctioned organizations/golden harvest.jpg"),
            'Reserved Officers Training Corps' => self::path("{$basePath}/university sanctioned organizations/rotc.jpg"),
            'Performing Guild' => self::path("{$basePath}/university sanctioned organizations/tau performing guild.jpg"),
            'Chorale' => self::path("{$basePath}/university sanctioned organizations/tau chorale.jpg"),
            'A.K.D.A.' => self::path("{$basePath}/university sanctioned organizations/akda.jpg"),
            'College of Agriculture and Forestry - Student Council' => self::path("{$basePath}/student government category/CAF SC.JPG"),
            'College of Arts and Sciences - Student Council' => self::path("{$basePath}/student government category/CAS SC.JPG"),
            'College of Veterinary Medicine - Student Council' => self::path("{$basePath}/student government category/CVM SC.JPG"),
            'College of Engineering and Technology - Student Council' => self::path("{$basePath}/student government category/CET SC.JPG"),
            'College of Business Management - Student Council' => self::path("{$basePath}/student government category/CBM SC.JPG"),
            'College of Education - Student Council' => self::path("{$basePath}/student government category/EDUC SC.jpg"),
            'Laboratory School - Student Council' => self::path("{$basePath}/student government category/LS SC.JPG"),
            'Supreme Student Council' => self::path("{$basePath}/student government category/TAU SC.JPG"),
        ];
    }

    public static function path(string $relativePath): string
    {
        $segments = array_map('rawurlencode', explode('/', $relativePath));

        return asset(implode('/', $segments));
    }
}
