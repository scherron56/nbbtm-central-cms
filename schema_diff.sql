<pre>-- MySQL dump 10.13  Distrib 8.4.11, for Linux (x86_64)
 --
--- Host: localhost    Database: nbbtm_central
+-- Host: localhost    Database: nbbtm_central_bk
 -- ------------------------------------------------------
 -- Server version	8.4.11
 
@@ -28,7 +28,7 @@
   `family_id` int DEFAULT &apos;0&apos;,
   `is_spouse` tinyint DEFAULT &apos;0&apos;,
   PRIMARY KEY (`families_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=10382 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci DELAY_KEY_WRITE=1;
+) ENGINE=InnoDB AUTO_INCREMENT=10373 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci DELAY_KEY_WRITE=1;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -50,7 +50,7 @@
   KEY `idx_ip_action_time` (`ip_address`,`action`,`created_at`),
   KEY `idx_user_action_time` (`user_id`,`action`,`created_at`),
   CONSTRAINT `access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `nbbtm_users` (`user_id`) ON DELETE SET NULL
-) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=638 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -61,19 +61,16 @@
 /*!40101 SET @saved_cs_client     = @@character_set_client */;
 /*!50503 SET character_set_client = utf8mb4 */;
 CREATE TABLE `app_attachments` (
-  `attachment_id` int NOT NULL AUTO_INCREMENT,
+  `document_id` int NOT NULL AUTO_INCREMENT,
   `entity_type` enum(&apos;event&apos;,&apos;ministry&apos;,&apos;contact&apos;) NOT NULL,
-  `entity_id` int NOT NULL,
-  `doc_category_id` int NOT NULL,
-  `document_sub_type` varchar(50) DEFAULT NULL,
   `document_name` varchar(255) NOT NULL,
+  `document_short_name` varchar(50) DEFAULT NULL,
   `document_mime` varchar(100) NOT NULL,
   `document_size` int unsigned NOT NULL,
   `document_data` longblob NOT NULL,
   `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
-  PRIMARY KEY (`attachment_id`),
-  KEY `idx_entity` (`entity_type`,`entity_id`),
-  KEY `idx_category` (`doc_category_id`)
+  PRIMARY KEY (`document_id`),
+  KEY `idx_entity` (`entity_type`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
@@ -211,7 +208,7 @@
   KEY `idx_child` (`is_child`) /*!80000 INVISIBLE */,
   KEY `idx_member` (`is_member`),
   KEY `idx_lastfirst` (`last_name`,`first_name`) USING BTREE
-) ENGINE=InnoDB AUTO_INCREMENT=3119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=3122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -252,7 +249,7 @@
   `document_data` longblob NOT NULL,
   `uploaded_at` timestamp NULL DEFAULT NULL,
   PRIMARY KEY (`document_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -287,7 +284,7 @@
   KEY `idx_min_comm` (`min_comm_id`) /*!80000 INVISIBLE */,
   CONSTRAINT `fk_member_alliance_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`contact_id`) ON DELETE CASCADE ON UPDATE CASCADE,
   CONSTRAINT `fk_member_alliance_min_comm` FOREIGN KEY (`min_comm_id`) REFERENCES `ministry_committee` (`min_comm_id`) ON DELETE CASCADE ON UPDATE CASCADE
-) ENGINE=InnoDB AUTO_INCREMENT=1904 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=1937 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -415,7 +412,7 @@
   PRIMARY KEY (`user_id`),
   UNIQUE KEY `username` (`username`),
   UNIQUE KEY `email` (`email`)
-) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -505,7 +502,7 @@
   PRIMARY KEY (`budget_item_id`),
   KEY `fk_prg_evnt_item_event_idx` (`prg_evnt_id`),
   CONSTRAINT `fk_prg_evnt_item_event` FOREIGN KEY (`prg_evnt_id`) REFERENCES `programs_events` (`prg_evnt_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=12057 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=12047 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -525,7 +522,7 @@
   KEY `fk_prg_evnt_min_support_ministry_idx` (`min_comm_id`),
   CONSTRAINT `fk_prg_evnt_min_support_event` FOREIGN KEY (`prg_evnt_id`) REFERENCES `programs_events` (`prg_evnt_id`),
   CONSTRAINT `fk_prg_evnt_min_support_ministry` FOREIGN KEY (`min_comm_id`) REFERENCES `ministry_committee` (`min_comm_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=10019 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=10012 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -565,7 +562,7 @@
   PRIMARY KEY (`schedule_id`),
   KEY `fk_prg_evnt_schedules_evnt_id_idx` (`prg_evnt_id`),
   CONSTRAINT `fk_prg_evnt_schedules_evnt_id` FOREIGN KEY (`prg_evnt_id`) REFERENCES `programs_events` (`prg_evnt_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=22031 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=22026 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
@@ -599,7 +596,7 @@
   `modified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   `modified_by` int DEFAULT NULL,
   PRIMARY KEY (`prg_evnt_id`)
-) ENGINE=InnoDB AUTO_INCREMENT=11006 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
+) ENGINE=InnoDB AUTO_INCREMENT=11005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 /*!40101 SET character_set_client = @saved_cs_client */;
 
 --
<font color="#26A269"><b>clarice@Centra</b></font></pre>