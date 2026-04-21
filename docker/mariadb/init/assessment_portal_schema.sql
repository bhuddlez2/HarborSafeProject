CREATE TABLE `tblAgency` (
  `AgencyType` CHAR(36) NOT NULL DEFAULT (UUID()),
  PRIMARY KEY (`AgencyType`)
);

CREATE TABLE `tblUserCredentials` (
  `Email`    VARCHAR(100) NOT NULL,
  `Password` VARCHAR(64) NOT NULL,
  `Admin`    BOOLEAN NOT NULL,
  PRIMARY KEY (`Email`)
);

CREATE TABLE `tblLawEnforcementAgents` (
  `Email`                  VARCHAR(100) NOT NULL UNIQUE,
  `LawEnforcementAgencyType` CHAR(36) NOT NULL,
  `BadgeNumber`            VARCHAR(25) NOT NULL,
  PRIMARY KEY (`Email`),
  FOREIGN KEY (`LawEnforcementAgencyType`)
      REFERENCES `tblAgency`(`AgencyType`)
);

CREATE TABLE `tblAssessmentAnswers` (
  `AssessmentDocID` CHAR(36) NOT NULL DEFAULT (UUID()),
  `RiskIndicator1`  BOOLEAN NOT NULL,
  `RiskIndicator2`  BOOLEAN NOT NULL,
  `RiskIndicator3`  BOOLEAN NOT NULL,
  `RiskIndicator4`  BOOLEAN NOT NULL,
  `RiskIndicator5`  BOOLEAN NOT NULL,
  `RiskIndicator6`  BOOLEAN NOT NULL,
  `RiskIndicator7`  BOOLEAN NOT NULL,
  `RiskIndicator8`  BOOLEAN NOT NULL,
  `RiskIndicator9`  BOOLEAN NOT NULL,
  `RiskIndicator10` BOOLEAN NOT NULL,
  `RiskIndicator11` BOOLEAN NOT NULL,
  PRIMARY KEY (`AssessmentDocID`)
);

CREATE TABLE `tblPrivateAssessment` (
  `DocumentID`           CHAR(36)     NOT NULL DEFAULT (UUID()),
  `DateCreated`          DATETIME NOT NULL,
  `SubmitterFirstName`   VARCHAR(50),
  `SubmitterLastName`    VARCHAR(50),
  `SubmitterPhoneNumber` VARCHAR(20),
  `SubmitterEmail`       VARCHAR(100),
  `OffenderFirstName`    VARCHAR(50) NOT NULL,
  `OffenderLastName`     VARCHAR(50) NOT NULL,
  `OffenderSex`          VARCHAR(1) NOT NULL,
  `OffenderDOB`          DATE,
  `OffenderRelationship` VARCHAR(50) NOT NULL,
  `VictimFirstName`      VARCHAR(50) NOT NULL,
  `VictimLastName`       VARCHAR(50) NOT NULL,
  `VictimSex`            VARCHAR(1) NOT NULL,
  `VictimDOB`            DATE,
  `VictimSafePhoneNumber` VARCHAR(20),
  `IncidentCounty`       VARCHAR(50) NOT NULL,
  `AssessmentDocID`      CHAR(36),
  PRIMARY KEY (`DocumentID`),
  FOREIGN KEY (`AssessmentDocID`)
      REFERENCES `tblAssessmentAnswers`(`AssessmentDocID`)
);

CREATE TABLE `tblLawEnforcementAssessment` (
  `DocumentID`           CHAR(36)     NOT NULL DEFAULT (UUID()),
  `DateCreated`          DATETIME NOT NULL,
  `SubmitterEmail`       VARCHAR(100) NOT NULL,
  `OffenderFirstName`    VARCHAR(50) NOT NULL,
  `OffenderLastName`     VARCHAR(50) NOT NULL,
  `OffenderSex`          VARCHAR(1) NOT NULL,
  `OffenderDOB`          DATE NOT NULL,
  `OffenderRelationship` VARCHAR(50) NOT NULL,
  `VictimFirstName`      VARCHAR(50) NOT NULL,
  `VictimLastName`       VARCHAR(50) NOT NULL,
  `VictimSex`            VARCHAR(1) NOT NULL,
  `VictimDOB`            DATE NOT NULL,
  `VictimSafePhoneNumber` VARCHAR(20),
  `IncidentCounty`       VARCHAR(50) NOT NULL,
  `IncidentCity`         VARCHAR(50) NOT NULL,
  `AssessmentDocID`      CHAR(36),
  PRIMARY KEY (`DocumentID`),
  FOREIGN KEY (`AssessmentDocID`)
      REFERENCES `tblAssessmentAnswers`(`AssessmentDocID`),
  FOREIGN KEY (`SubmitterEmail`)
      REFERENCES `tblUserCredentials`(`Email`)
);

CREATE TABLE `tblAssessmentAnswerChangeLog` (
  `LogID`           CHAR(36) NOT NULL DEFAULT (UUID()),
  `AssessmentDocID` CHAR(36) NOT NULL,
  `ChangeField`     VARCHAR(32) NOT NULL,
  `PreviousValue`   BOOLEAN NOT NULL,
  `NewValue`        BOOLEAN NOT NULL,
  `ChangedBy`       VARCHAR(100) NOT NULL,
  `TimeStamp`       DATETIME NOT NULL,
  PRIMARY KEY (`LogID`),
  FOREIGN KEY (`ChangedBy`)
      REFERENCES `tblUserCredentials`(`Email`),
  FOREIGN KEY (`AssessmentDocID`)
      REFERENCES `tblAssessmentAnswers`(`AssessmentDocID`)
);

CREATE TABLE `tblAssessmentChangeLog` (
  `ChangeLogID`     CHAR(36) NOT NULL DEFAULT (UUID()),
  `AssessmentDocID` CHAR(36) NOT NULL,
  `ChangeField`     VARCHAR(32) NOT NULL,
  `PreviousValue`   VARCHAR(50) NOT NULL,
  `NewValue`        VARCHAR(50) NOT NULL,
  `AssessmentChange` BOOLEAN NOT NULL,
  `LogID`           CHAR(36) NOT NULL,
  `ChangedBy`       VARCHAR(100) NOT NULL,
  `TimeStamp`       DATETIME NOT NULL,
  PRIMARY KEY (`ChangeLogID`),
  FOREIGN KEY (`LogID`)
      REFERENCES `tblAssessmentAnswerChangeLog`(`LogID`),
  FOREIGN KEY (`ChangedBy`)
      REFERENCES `tblUserCredentials`(`Email`),
  FOREIGN KEY (`AssessmentDocID`)
      REFERENCES `tblLawEnforcementAssessment`(`DocumentID`)
);