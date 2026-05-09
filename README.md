<div align="center">

# 🎓 UniLearn

### Plateforme d'Apprentissage Intelligente

**Application Web Symfony — E-learning · Quiz & Certification · Recommandation IA · Marketplace Étudiante**

[![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=flat-square&logo=symfony&logoColor=white)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB_10.4-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mariadb.org)
[![Doctrine](https://img.shields.io/badge/Doctrine_ORM-3.x-F36D45?style=flat-square)](https://doctrine-project.org)
[![Twig](https://img.shields.io/badge/Twig-Templating-FF6B6B?style=flat-square)](https://twig.symfony.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat-square&logo=docker&logoColor=white)](https://docker.com)
[![License](https://img.shields.io/badge/Licence-Académique-blue?style=flat-square)](LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/tebourbimalek/pi-websymfony?style=flat-square&logo=github&color=yellow)](https://github.com/tebourbimalek/pi-websymfony)
[![Railway](https://img.shields.io/badge/Déployé-Railway-0B0D0E?style=flat-square&logo=railway&logoColor=white)](https://railway.app)

</div>

---

## 🚀 Aperçu Rapide

| | |
|---|---|
| **Projet** | UniLearn — Plateforme d'Apprentissage Intelligente |
| **Type** | Application Web Symfony — E-learning (Projet Intégré Ingénieur) |
| **Framework** | Symfony 6.4 + PHP 8.2 |
| **Base de données** | MySQL / MariaDB 10.4 |
| **Architecture** | MVC + DAO + Couches Service |
| **IA** | Google Gemini · Hugging Face · Groq |
| **Authentification** | JWT · OAuth2 Google/Facebook · Reconnaissance Faciale |
| **Déploiement** | Docker · Railway · PHP Built-in Server |
| **Statut** | ✅ En production |

> UniLearn est une plateforme e-learning nouvelle génération développée avec Symfony 6.4, qui combine apprentissage en ligne, évaluation automatisée, certification PDF, assistance IA et marketplace étudiante dans une architecture MVC robuste et extensible.

---

## 🌐 Démo en Ligne

| Environnement | URL | Statut |
|:---|:---|:---|
| 🚀 **Production** | [UniLearn sur Railway](https://pi-websymfony-production.up.railway.app/) | ![Deploy](https://img.shields.io/badge/Actif-✓-brightgreen?style=flat-square) |
| 🖥️ **Local** | `http://127.0.0.1:8000` | ![Dev](https://img.shields.io/badge/Développement-✓-blue?style=flat-square) |

## ✨ Fonctionnalités Principales

### 🔐 Authentification & Sécurité

| Fonctionnalité | Description | Statut |
|:---|:---|:---|
| Inscription / Connexion | Hachage sécurisé des mots de passe (bcrypt) | ✅ |
| Google OAuth 2.0 | Authentification via compte Google | ✅ |
| Facebook Login | Authentification via compte Facebook | ✅ |
| Reconnaissance Faciale | Login biométrique via FaceAuth API | ✅ |
| JWT Tokens | Authentification API stateless | ✅ |
| Gestion des Rôles | Étudiant · Enseignant · Administrateur | ✅ |
| Réinitialisation Mot de Passe | Envoi d'email avec lien sécurisé | ✅ |
| Google reCAPTCHA v3 | Protection anti-bot sur les formulaires | ✅ |

### 📚 Système d'Apprentissage

| Fonctionnalité | Description | Statut |
|:---|:---|:---|
| Gestion des Cours | CRUD complet — titre, description, catégorie, niveau | ✅ |
| Chapitres & Leçons | Organisation hiérarchique du contenu | ✅ |
| Suivi de Progression | Barre de progression dynamique en temps réel | ✅ |
| Indicateurs Visuels | Non commencé → En cours → Complété (color-coded) | ✅ |
| Système « Continue Learning » | Reprise automatique à la dernière leçon | ✅ |
| Recommandations Personnalisées | Vidéos YouTube · Livres Google Books | ✅ |
| Tableau de Bord Étudiant | Statistiques, cours récents, progression globale | ✅ |
| Tableau de Bord Enseignant | Gestion des cours, suivi des étudiants | ✅ |

### 📝 Quiz & Certification

| Fonctionnalité | Description | Statut |
|:---|:---|:---|
| Quiz Interactifs | Questions à choix multiples, timer, navigation | ✅ |
| Scoring Automatique | Calcul du score en temps réel | ✅ |
| Seuil de Réussite | 80% minimum pour valider le cours | ✅ |
| Historique des Tentatives | Suivi complet des performances | ✅ |
| Génération Certificats PDF | Certificat professionnel avec wkhtmltopdf | ✅ |
| Boutons Dynamiques | Continue → Take Quiz → View Certificate | ✅ |
| Vérification de Certificat | Page publique de vérification d'authenticité | ✅ |

### 🤖 Fonctionnalités IA

| Fonctionnalité | Service | Statut |
|:---|:---|:---|
| Chatbot d'Assistance | Google Gemini API | ✅ |
| Recommandations de Contenu | Hugging Face API | ✅ |
| Inférence Rapide LLM | Groq API | ✅ |
| Traduction Automatique | Google Translate API | ✅ |
| Suggestions Pédagogiques | Vertex AI (Gemini 1.5 Flash) | ✅ |

### 💼 Marketplace Étudiante

| Fonctionnalité | Description | Statut |
|:---|:---|:---|
| Place de Marché | Publication de services étudiants | ✅ |
| Micro-Services | Tutorat, rédaction CV, aide aux devoirs | ✅ |
| Système de Commandes | Gestion complète des commandes | ✅ |
| Profils Freelances | Portfolio et évaluations étudiants | ✅ |

### 🔍 Recherche Avancée

| Fonctionnalité | Description | Statut |
|:---|:---|:---|
| Recherche Full-Text | Elasticsearch intégré | ✅ |
| Filtrage Intelligent | Par catégorie, niveau, durée | ✅ |
| Indexation Automatique | Cours et contenus indexés en temps réel | ✅ |

---

## 📸 Captures d'Écran

<table>
<tr>
<td align="center"><b>🏠 Page d'Accueil</b></td>
<td align="center"><b>📝 Inscription</b></td>
</tr>
<tr>
<td><img src="https://via.placeholder.com/600x350?text=Page+Accueil+UniLearn" alt="Accueil"/></td>
<td><img src="https://via.placeholder.com/600x350?text=Page+Inscription" alt="Inscription"/></td>
</tr>
<tr>
<td align="center"><b>📚 Mes Cours</b></td>
<td align="center"><b>📖 Leçon</b></td>
</tr>
<tr>
<td><img src="https://via.placeholder.com/600x350?text=Mes+Cours+Progression" alt="Mes Cours"/></td>
<td><img src="https://via.placeholder.com/600x350?text=Vue+Leçon+Contenu" alt="Leçon"/></td>
</tr>
<tr>
<td align="center"><b>❓ Quiz Interactif</b></td>
<td align="center"><b>🏆 Certificat PDF</b></td>
</tr>
<tr>
<td><img src="https://via.placeholder.com/600x350?text=Quiz+Interactif" alt="Quiz"/></td>
<td><img src="https://via.placeholder.com/600x350?text=Certificat+PDF" alt="Certificat"/></td>
</tr>
<tr>
<td align="center"><b>💼 Marketplace</b></td>
<td align="center"><b>🤖 Assistant IA</b></td>
</tr>
<tr>
<td><img src="https://via.placeholder.com/600x350?text=Marketplace+Etudiante" alt="Marketplace"/></td>
<td><img src="https://via.placeholder.com/600x350?text=Chatbot+Gemini" alt="IA"/></td>
</tr>
</table>

---

## 🏗️ Architecture Système

### Vue d'Ensemble

```
┌──────────────────────────────────────────────────────────────────┐
│                        COUCHE PRÉSENTATION                       │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────────────┐   │
│  │    Twig      │  │  Bootstrap 5 │  │  JavaScript / AJAX   │   │
│  │  Templates   │  │   + CSS 3    │  │  Turbo / Hotwire     │   │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬────────────┘   │
├─────────┼──────────────────┼─────────────────────┼───────────────┤
│         │        COUCHE CONTRÔLEUR                │               │
│  ┌──────▼──────────────────▼─────────────────────▼────────────┐  │
│  │              Symfony 6.4 Controllers                        │  │
│  │  CourseCtrl · LessonCtrl · QuizCtrl · EnrollmentCtrl       │  │
│  │  CertificateCtrl · AuthCtrl · MarketCtrl · BookingCtrl     │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
├─────────────────────────────┼────────────────────────────────────┤
│                    COUCHE SERVICE                                │
│  ┌──────────────────────────▼─────────────────────────────────┐  │
│  │  FaceAuthService · GeminiService · MailerService            │  │
│  │  RecommendationService · PdfService · SearchService         │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
├─────────────────────────────┼────────────────────────────────────┤
│                    COUCHE PERSISTENCE                            │
│  ┌──────────────────────────▼─────────────────────────────────┐  │
│  │              Doctrine ORM 3.x                               │  │
│  │  Entities · Repositories · Migrations · Fixtures            │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
├─────────────────────────────┼────────────────────────────────────┤
│                    BASE DE DONNÉES                              │
│  ┌──────────────────────────▼─────────────────────────────────┐  │
│  │           MySQL / MariaDB 10.4                              │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Flux de Données — Progression Étudiant

```
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌───────────────┐
│  Étudiant │───▶│  Complète    │───▶│  Met à jour  │───▶│  Affiche      │
│  navigue  │    │  une leçon   │    │  progression │    │  progression  │
└──────────┘    └──────────────┘    └──────────────┘    └───────────────┘
                      │                    │                     │
                      ▼                    ▼                     ▼
               POST /lesson/{id}    enrollment.progress    Barre dynamique
               /complete            recalculée via DB      + bouton contextuel
                                                          (Continue / Quiz / Cert)
```

### Flux d'Authentification Multi-Facteurs

```
                    ┌─────────────┐
                    │   Login Page │
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌──────────┐ ┌──────────┐ ┌──────────┐
        │  Email   │ │  Google  │ │ Facebook │
        │ Password │ │  OAuth   │ │  Login   │
        └────┬─────┘ └────┬─────┘ └────┬─────┘
             │             │             │
             ▼             ▼             ▼
        ┌──────────────────────────────────────┐
        │         Vérification reCAPTCHA        │
        └──────────────────┬───────────────────┘
                           │
                    ┌──────▼──────┐
                    │ Face ID ?   │
                    │ (Optionnel) │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
                    │  JWT Token  │
                    │  + Session  │
                    └─────────────┘
```

---

## 🛠️ Stack Technique

### Backend

| Technologie | Version | Rôle |
|:---|:---|:---|
| ![PHP](https://img.shields.io/badge/-PHP_8.2-777BB4?style=flat-square&logo=php&logoColor=white) | 8.2 | Langage serveur principal |
| ![Symfony](https://img.shields.io/badge/-Symfony_6.4-000000?style=flat-square&logo=symfony&logoColor=white) | 6.4 LTS | Framework MVC full-stack |
| ![Doctrine](https://img.shields.io/badge/-Doctrine_ORM-F36D45?style=flat-square) | 3.x | Mapping objet-relationnel |
| ![MySQL](https://img.shields.io/badge/-MySQL-4479A1?style=flat-square&logo=mysql&logoColor=white) | MariaDB 10.4 | Base de données relationnelle |
| JWT | LexikJWTAuthenticationBundle | Authentification API stateless |
| Maker Bundle | Symfony Maker | Génération de code scaffolding |
| Migration Bundle | Doctrine Migrations | Versionning du schéma BDD |
| Fixture Bundle | Doctrine Fixtures | Données de test |

### Frontend

| Technologie | Rôle |
|:---|:---|
| ![Twig](https://img.shields.io/badge/-Twig-FF6B6B?style=flat-square) | Moteur de templates serveur |
| ![Bootstrap](https://img.shields.io/badge/-Bootstrap_5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white) | Framework CSS responsive |
| Font Awesome 6 | Bibliothèque d'icônes vectorielles |
| JavaScript ES6+ | Interactions côté client |
| AJAX / Fetch API | Requêtes asynchrones |
| Turbo / Hotwire | Navigation SPA sans rechargement |

### APIs & Services Externes

| Service | Fournisseur | Usage |
|:---|:---|:---|
| ![Google](https://img.shields.io/badge/-Gemini_API-4285F4?style=flat-square&logo=google&logoColor=white) | Google AI | Chatbot d'assistance pédagogique |
| Google OAuth 2.0 | Google Cloud | Authentification sociale |
| Facebook Login | Meta for Developers | Authentification sociale |
| Google reCAPTCHA v3 | Google Cloud | Protection anti-bot |
| Google Translate API | Google Cloud | Traduction automatique multilingue |
| Google Books API | Google Cloud | Recommandations de livres |
| YouTube Data API v3 | Google Cloud | Recommandations de vidéos éducatives |
| Hugging Face Inference | Hugging Face | Modèles IA de recommandation |
| Groq API | Groq | Inférence LLM ultra-rapide |
| Vertex AI | Google Cloud | Gemini 1.5 Flash — suggestions pédagogiques |
| Elasticsearch | Elastic | Recherche full-text et indexation |
| Gmail SMTP | Google | Envoi d'emails transactionnels |
| KnpSnappy + wkhtmltopdf | KNP Labs | Génération de certificats PDF |

### DevOps & Infrastructure

| Outil | Rôle |
|:---|:---|
| ![Docker](https://img.shields.io/badge/-Docker-2496ED?style=flat-square&logo=docker&logoColor=white) | Conteneurisation de l'application |
| Docker Compose | Orchestration multi-conteneurs |
| ![Railway](https://img.shields.io/badge/-Railway-0B0D0E?style=flat-square&logo=railway&logoColor=white) | Hébergement cloud PaaS |
| ![GitHub](https://img.shields.io/badge/-GitHub-181717?style=flat-square&logo=github&logoColor=white) | Versionning collaboratif & CI/CD |
| Composer | Gestion des dépendances PHP |
| PHP Built-in Server | Serveur de développement local |

---


## 🧩 Modules du Projet

Le système est découpé en **5 modules fonctionnels**, chacun assigné à un membre de l'équipe :

| # | Module | Entités Clés | Fonctionnalités | Responsable |
|:---|:---|:---|:---|:---|
| 1 | 🔐 **Authentification & Utilisateurs** | `User`, `Role` | Inscription, connexion, OAuth, Face ID, JWT, rôles, mot de passe oublié | Tebourbi Malek |
| 2 | 📚 **Cours & Recommandation** | `Course`, `Chapter`, `Lesson`, `Recommendation` | CRUD cours, leçons, progression, recommandations IA, tableaux de bord | Membre 2 |
| 3 | 📝 **Quiz & Certification** | `Quiz`, `Question`, `QuizResult`, `Certificate` | Quiz interactifs, scoring, certificats PDF, historique des tentatives | Membre 3 |
| 4 | 📅 **Réservation & Séances** | `Booking`, `Session`, `Teacher` | Planification, réservation, séances en ligne, disponibilités | Membre 4 |
| 5 | 💼 **Marketplace & Freelance** | `Product`, `Order`, `Job` | Marketplace, micro-services, commandes, profils freelances | Membre 5 |

## 📊 Statistiques du Projet

| Métrique | Valeur |
|:---|:---|
| 📂 Entités Doctrine | 16+ |
| 🎮 Contrôleurs | 12+ |
| 🛣️ Routes définies | 80+ |
| 📄 Templates Twig | 50+ |
| 📦 Dépendances Composer | 60+ |
| 🤖 APIs externes intégrées | 12 |
| 🐳 Docker-ready | ✅ |
| ☁️ Cloud déployé | ✅ (Railway) |
| 🧪 Fixtures de test | ✅ |
| 📝 Migrations BDD | Versionnées |

---

## 🧠 Défis Techniques Résolus

### 1. Progression Dynamique Cohérente

**Problème** : La progression affichée sur la page « Mes Cours » ne correspondait pas à l'état réel des complétions de leçons en base de données. Le champ `enrollment.progress` était mis à jour de manière asynchrone et parfois incorrecte.

**Solution** : Recalcul systématique de la progression directement depuis la table `lesson_completion` à chaque affichage, en utilisant une requête DQL optimisée qui compte les leçons complétées par rapport au total des leçons du cours.

### 2. Boutons Contextuels (Continue → Quiz → Certificate)

**Problème** : Le bouton d'action restait « Continue Learning » même après complétion de toutes les leçons et réussite du quiz.

**Solution** : Système de boutons dynamiques basé sur l'état réel en BDD : `hasCertificate` → View Certificate, `quizPassed` → View Certificate, `progress 100%` → Take Quiz, `progress <100%` → Continue Learning.

### 3. Conflit de Routing QuizController / QuizAttemptController

**Problème** : Deux contrôleurs définissaient la même route `quiz_take`, causant un comportement imprévisible selon l'ordre de chargement.

**Solution** : Séparation claire des responsabilités — un seul contrôleur gère la route `quiz_take`.

### 4. Chargement Paresseux (Lazy Loading) des Collections

**Problème** : `$course->getChapters()->count()` retournait 0 car les chapitres n'étaient pas chargés en mémoire, faussant le calcul du nombre total de leçons.

**Solution** : Utilisation de requêtes explicites via les Repository pour compter les leçons directement en BDD, sans dépendre du chargement paresseux.

### 5. Turbo / Hotwire et Formulaires Symfony

**Problème** : Turbo interceptait les soumissions de formulaires d'inscription et exigeait une réponse de redirection (3xx), alors que le contrôleur renvoyait du JSON ou du HTML 200.

**Solution** : Désactivation de Turbo sur les formulaires d'authentification via `data-turbo="false"` et filtrage des requêtes Turbo dans le contrôleur.

### 6. Certificats avec quizResult Null

**Problème** : Certains enregistrements `Certificate` avaient `quiz_result_id = NULL`, causant des erreurs Twig lors de l'accès aux propriétés de la relation.

**Solution** : Vérifications null-safe dans les templates Twig avec l'opérateur ternaire pour éviter l'accès à des propriétés sur des objets null.

---

## 👥 Équipe de Développement

| Membre | Module | Technologies | Rôle |
|:---|:---|:---|:---|
| **Tebourbi Malek** | 🔐 Authentification & Utilisateurs | Symfony, PHP, JWT, OAuth, FaceAuth, Docker | Développeur Full-Stack |
| **Membre 2** | 📚 Cours & Recommandation | Symfony, Doctrine, Elasticsearch, Gemini AI | Développeur Full-Stack |
| **Membre 3** | 📝 Quiz & Certification | Symfony, Doctrine, wkhtmltopdf, Twig | Développeur Full-Stack |
| **Membre 4** | 📅 Réservation & Séances | Symfony, Doctrine, FullCalendar | Développeur Full-Stack |
| **Membre 5** | 💼 Marketplace & Freelance | Symfony, Doctrine, Stripe/Payment | Développeur Full-Stack |

> 🎓 *Étudiants ingénieurs — École Supérieure d'Informatique (ESI)*
> 
> 📅 *Année universitaire 2024–2025 — Module Projet Intégré*

---

## 🏷️ GitHub Topics

```
symfony  ·  e-learning  ·  lms  ·  php  ·  education-platform  ·  quiz-system  ·  
certificate-generation  ·  doctrine-orm  ·  mysql  ·  twig  ·  google-gemini  ·  
face-recognition  ·  marketplace  ·  jwt-authentication  ·  elasticsearch  ·  
pdf-generation  ·  oauth2  ·  recaptcha  ·  bootstrap5  ·  docker  ·  
recommendation-system  ·  chatbot  ·  student-platform
```

**Mots-clés académiques :**

```
projet-intégré  ·  développement-web  ·  plateforme-éducative  ·  
système-de-recommandation  ·  apprentissage-en-ligne  ·  évaluation-automatique  ·  
certification-numérique  ·  architecture-mvc  ·  génie-logiciel  ·  méthodologie-agile  ·  
scrum  ·  symfony-6  ·  php-8
```

---

## 📄 Licence

Ce projet est réalisé dans un **cadre académique** — Projet Intégré (PI) — dans le cursus d'ingénieur en informatique à l'École Supérieure d'Informatique (ESI).

Il n'est pas destiné à un usage commercial sans autorisation préalable des auteurs.

© 2024–2025 — Équipe UniLearn. Tous droits réservés.

---

<div align="center">

<br>

**« Conçu avec Symfony. Pensé pour l'apprentissage. Développé pour l'avenir. »**

<br>

[![GitHub](https://img.shields.io/badge/Repo-GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/tebourbimalek/pi-websymfony)
[![Railway](https://img.shields.io/badge/Démo-Railway-0B0D0E?style=for-the-badge&logo=railway&logoColor=white)](https://railway.app)

<br>

*Construit avec ❤️ et beaucoup de ☕ par l'équipe UniLearn*

</div>
