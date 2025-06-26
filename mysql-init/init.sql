-- MySQL dump 10.13  Distrib 8.0.41, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: farmerfact
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `chats`
--

DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `english_text` varchar(255) NOT NULL,
  `tamil_text` varchar(255) NOT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chats`
--

LOCK TABLES `chats` WRITE;
/*!40000 ALTER TABLE `chats` DISABLE KEYS */;
/*!40000 ALTER TABLE `chats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crop_suggestion`
--

DROP TABLE IF EXISTS `crop_suggestion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crop_suggestion` (
  `id` int NOT NULL,
  `english_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamil_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crop_suggestion`
--

LOCK TABLES `crop_suggestion` WRITE;
/*!40000 ALTER TABLE `crop_suggestion` DISABLE KEYS */;
INSERT INTO `crop_suggestion` VALUES (1,'Rice','அரிசி'),(2,'Wheat','கோதுமை'),(3,'Maize','சோளம்'),(4,'Sugarcane','கரும்பு'),(5,'Cotton','பருத்தி'),(6,'Groundnut','நிலக்கடலை'),(7,'Millet','சிறுதானியம்'),(8,'Tomato','தக்காளி'),(9,'Brinjal','கத்திரிக்காய்'),(10,'Chili','மிளகாய்');
/*!40000 ALTER TABLE `crop_suggestion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crop_suggestion_climatic_condition`
--

DROP TABLE IF EXISTS `crop_suggestion_climatic_condition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crop_suggestion_climatic_condition` (
  `id` int NOT NULL,
  `crop_suggestion_id` int NOT NULL,
  `english_climatic_condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamil_climatic_condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crop_suggestion_id` (`crop_suggestion_id`,`english_climatic_condition`),
  CONSTRAINT `crop_suggestion_climatic_condition_ibfk_1` FOREIGN KEY (`crop_suggestion_id`) REFERENCES `crop_suggestion` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crop_suggestion_climatic_condition`
--

LOCK TABLES `crop_suggestion_climatic_condition` WRITE;
/*!40000 ALTER TABLE `crop_suggestion_climatic_condition` DISABLE KEYS */;
INSERT INTO `crop_suggestion_climatic_condition` VALUES (1,1,'Tropical','உயர் வெப்பநிலை'),(2,2,'Temperate','நாகரிக'),(3,3,'Tropical','உயர் வெப்பநிலை'),(4,4,'Subtropical','இருபதாண்டு'),(5,5,'Tropical','உயர் வெப்பநிலை'),(6,6,'Temperate','நாகரிக'),(7,7,'Tropical','உயர் வெப்பநிலை'),(8,8,'Temperate','நாகரிக'),(9,9,'Subtropical','இருபதாண்டு'),(10,10,'Tropical','உயர் வெப்பநிலை');
/*!40000 ALTER TABLE `crop_suggestion_climatic_condition` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crop_suggestion_months`
--

DROP TABLE IF EXISTS `crop_suggestion_months`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crop_suggestion_months` (
  `id` int NOT NULL,
  `crop_suggestion_id` int NOT NULL,
  `month` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crop_suggestion_id` (`crop_suggestion_id`,`month`),
  CONSTRAINT `crop_suggestion_months_ibfk_1` FOREIGN KEY (`crop_suggestion_id`) REFERENCES `crop_suggestion` (`id`),
  CONSTRAINT `crop_suggestion_months_chk_1` CHECK (((`month` >= 1) and (`month` <= 12)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crop_suggestion_months`
--

LOCK TABLES `crop_suggestion_months` WRITE;
/*!40000 ALTER TABLE `crop_suggestion_months` DISABLE KEYS */;
INSERT INTO `crop_suggestion_months` VALUES (1,1,1),(2,1,2),(3,2,3),(4,3,5),(5,4,7),(6,5,8),(7,6,6),(8,7,9),(9,8,10),(10,9,11),(11,10,12);
/*!40000 ALTER TABLE `crop_suggestion_months` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crop_suggestion_soil_types`
--

DROP TABLE IF EXISTS `crop_suggestion_soil_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crop_suggestion_soil_types` (
  `id` int NOT NULL,
  `crop_suggestion_id` int NOT NULL,
  `english_soil_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamil_soil_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crop_suggestion_id` (`crop_suggestion_id`,`english_soil_type`),
  CONSTRAINT `crop_suggestion_soil_types_ibfk_1` FOREIGN KEY (`crop_suggestion_id`) REFERENCES `crop_suggestion` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crop_suggestion_soil_types`
--

LOCK TABLES `crop_suggestion_soil_types` WRITE;
/*!40000 ALTER TABLE `crop_suggestion_soil_types` DISABLE KEYS */;
INSERT INTO `crop_suggestion_soil_types` VALUES (1,1,'Loamy','உருவான மண்'),(2,2,'Clay','கல்லு மண்'),(3,3,'Sandy','மண்'),(4,4,'Loamy','உருவான மண்'),(5,5,'Clay','கல்லு மண்'),(6,6,'Sandy','மண்'),(7,7,'Loamy','உருவான மண்'),(8,8,'Clay','கல்லு மண்'),(9,9,'Loamy','உருவான மண்'),(10,10,'Sandy','மண்');
/*!40000 ALTER TABLE `crop_suggestion_soil_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fertilizer_suggestion`
--

DROP TABLE IF EXISTS `fertilizer_suggestion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fertilizer_suggestion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `english_name` varchar(255) NOT NULL,
  `tamil_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fertilizer_suggestion`
--

LOCK TABLES `fertilizer_suggestion` WRITE;
/*!40000 ALTER TABLE `fertilizer_suggestion` DISABLE KEYS */;
/*!40000 ALTER TABLE `fertilizer_suggestion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fertilizer_suggestion_months`
--

DROP TABLE IF EXISTS `fertilizer_suggestion_months`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fertilizer_suggestion_months` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fertilizer_suggestion_id` int NOT NULL,
  `month` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fertilizer_suggestion_id` (`fertilizer_suggestion_id`,`month`),
  CONSTRAINT `fertilizer_suggestion_months_ibfk_1` FOREIGN KEY (`fertilizer_suggestion_id`) REFERENCES `fertilizer_suggestion` (`id`),
  CONSTRAINT `fertilizer_suggestion_months_chk_1` CHECK (((`month` >= 1) and (`month` <= 12)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fertilizer_suggestion_months`
--

LOCK TABLES `fertilizer_suggestion_months` WRITE;
/*!40000 ALTER TABLE `fertilizer_suggestion_months` DISABLE KEYS */;
/*!40000 ALTER TABLE `fertilizer_suggestion_months` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fertilizer_suggestion_soil_types`
--

DROP TABLE IF EXISTS `fertilizer_suggestion_soil_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fertilizer_suggestion_soil_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fertilizer_suggestion_id` int NOT NULL,
  `english_soil_type` varchar(255) NOT NULL,
  `tamil_soil_type` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fertilizer_suggestion_id` (`fertilizer_suggestion_id`,`english_soil_type`),
  CONSTRAINT `fertilizer_suggestion_soil_types_ibfk_1` FOREIGN KEY (`fertilizer_suggestion_id`) REFERENCES `fertilizer_suggestion` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fertilizer_suggestion_soil_types`
--

LOCK TABLES `fertilizer_suggestion_soil_types` WRITE;
/*!40000 ALTER TABLE `fertilizer_suggestion_soil_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `fertilizer_suggestion_soil_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `otp_verifications`
--

DROP TABLE IF EXISTS `otp_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_verifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `otp` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `purpose` enum('signup','password_reset') DEFAULT 'signup',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `otp_verifications`
--

LOCK TABLES `otp_verifications` WRITE;
/*!40000 ALTER TABLE `otp_verifications` DISABLE KEYS */;
INSERT INTO `otp_verifications` VALUES (1,'johndoe@gmail.com',879491,'2025-04-21 16:02:10','signup'),(2,'adithyakrishna705@gmail.com',661854,'2025-04-21 16:06:24','signup'),(13,'adithyakrishnapn@gmail.com',887180,'2025-04-29 08:11:45','password_reset');
/*!40000 ALTER TABLE `otp_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pref_lang` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `users_chk_1` CHECK ((`role` in (_utf8mb4'admin',_utf8mb4'farmer',_utf8mb4'support')))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User','$2y$10$ivjPYkALoK7CZpZap3x5nePtC7gC2GioiEV.83O3hYLTz3od35zr2','test@example.com','farmer','2025-04-21 15:58:23',NULL),(2,'John Doe','$2y$10$qHdwgUNvXogu99D0T4O.nO6vbL6Ry5WZFEwRPZy3kxWdGFo44MiPC','johndoe@gmail.com','farmer','2025-04-21 16:02:10',NULL),(6,'Adithya yo','$2y$10$mM6JpgEsHtLo4PtjZd.mxelOIviDyWYTbO3PxD1tRVXBJ9Z5kGPBi','adithyakrishnapn@gmail.com','farmer','2025-04-21 16:32:04','English');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-30 18:14:24
