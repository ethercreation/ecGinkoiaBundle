# Ginkoia Bundle

Synchronisation de pimcore et Ginkoia

## Description

Crée des crons et des hooks pour créer dans Pimcore les produits et mettre à jour les prix et envoyer les commandes vers Ginkoia.

## Features

- **Import du catalogue** (cron)  
- **Mise à jour des stocks et prix** (cron)
- **Export des commandes** (hook)

## Requirements

- PHP 8.0 or higher
- Pimcore 6
- [ecMiddle](https://github.com/gitethercreation/ecmiddle)
- SimpleXMLElement

## Installation

Une fois les dépendances vérifiées, cloner le projet dans le dossier **/bundles** de l'app

```bash 
git clone git@github.com:gitethercreation/ecGinkoiaBundle.git bundles/ecGinkoiaBundle
```

Ajout du bundle dans `bundles.php` 
```php
// bundles.php

// ...

return [
    /*...*/
    bundles\ecGinkoiaBundle\ecGinkoiaBundle::class => ['all' => true],
];
```

## Usage

Tout se déroule en cron et en hook. Il n'y a donc rien à faire pour l'utiliser.
Pour lancer des tests, aller dans **Paramètres > Crons** puis cliquer sur *"Lancer"*.
![screencast](media/screen-ginkoia-crons.gif)

## Configuration

Dans le front office, configurer les paramètres nécessaires dans **Paramètres > Configuration > Ginkoia**.
![screencast](media/screen-ginkoia-config.gif)

## License

`L'utilisation de ce fichier source est soumise à une licence commerciale concédée par la société Ether Création`
`Toute utilisation, reproduction, modification ou distribution du present fichier source sans contrat de licence écrit de la part de la SARL Ether Création est expressément interdite.`
`Pour obtenir une licence, veuillez contacter la SARL Ether Création à l'adresse : contact@ethercreation.com`

## Contact

Information de contact - [Théodore Riant] - theodore@ethercreation.com

Lien du projet: [https://github.com/gitethercreation/ecGinkoiaBundle](https://github.com/gitethercreation/ecGinkoiaBundle)
