-- ============================================================
-- SMART EVENT MANAGEMENT SYSTEM
-- Oracle SQL — Complete Schema Script
-- Reconstructed from SQL*Plus screenshots + PHP backend source
-- ============================================================
-- ORDER: Tables → ALTER TABLE → Sequences → Triggers → Views → Inserts
-- ============================================================


-- ============================================================
-- SECTION 1: CREATE TABLES
-- ============================================================

-- NOTE: ADMIN is a reserved word in Oracle.
-- Wrap in double-quotes everywhere it is referenced as a table name.
CREATE TABLE "ADMIN" (
    AdminID    VARCHAR2(5)  PRIMARY KEY,
    Name       VARCHAR2(50) NOT NULL,
    Email      VARCHAR2(50) UNIQUE NOT NULL,
    Password   VARCHAR2(50) NOT NULL
);

CREATE TABLE DEPARTMENT (
    DepartmentID   VARCHAR2(5)  PRIMARY KEY,
    DepartmentName VARCHAR2(50) UNIQUE NOT NULL
);

CREATE TABLE STUDENT (
    StudentID    VARCHAR2(6) PRIMARY KEY,
    Name         VARCHAR2(50) NOT NULL,
    DepartmentID VARCHAR2(5)  NOT NULL,
    Year         NUMBER       CHECK (Year BETWEEN 1 AND 4),
    DOB          DATE         NOT NULL,
    CONSTRAINT fk_student_dept FOREIGN KEY (DepartmentID)
        REFERENCES DEPARTMENT(DepartmentID)
);

CREATE TABLE COORDINATOR (
    CoordinatorID    VARCHAR2(5)  PRIMARY KEY,
    Name             VARCHAR2(50) NOT NULL,
    DepartmentID     VARCHAR2(5)  NOT NULL,
    CreatedByAdminID VARCHAR2(5)  NOT NULL,
    CONSTRAINT fk_coord_dept  FOREIGN KEY (DepartmentID)
        REFERENCES DEPARTMENT(DepartmentID),
    CONSTRAINT fk_coord_admin FOREIGN KEY (CreatedByAdminID)
        REFERENCES "ADMIN"(AdminID)
);

CREATE TABLE VENUE (
    VenueID   VARCHAR2(5)  PRIMARY KEY,
    VenueName VARCHAR2(50) UNIQUE NOT NULL,
    Capacity  NUMBER       CHECK (Capacity > 0)
);

CREATE TABLE EVENTCATEGORY (
    CategoryID   VARCHAR2(6)  PRIMARY KEY,
    CategoryName VARCHAR2(50) UNIQUE NOT NULL
);

CREATE TABLE EVENT (
    EventID         VARCHAR2(5)   PRIMARY KEY,
    EventName       VARCHAR2(100) NOT NULL,
    EventDate       DATE          NOT NULL,
    BudgetAllocated NUMBER        CHECK (BudgetAllocated >= 0),
    CategoryID      VARCHAR2(6)   NOT NULL,
    VenueID         VARCHAR2(5)   NOT NULL,
    CoordinatorID   VARCHAR2(5)   NOT NULL,
    CONSTRAINT fk_event_category FOREIGN KEY (CategoryID)
        REFERENCES EVENTCATEGORY(CategoryID),
    CONSTRAINT fk_event_venue FOREIGN KEY (VenueID)
        REFERENCES VENUE(VenueID),
    CONSTRAINT fk_event_coord FOREIGN KEY (CoordinatorID)
        REFERENCES COORDINATOR(CoordinatorID)
);

CREATE TABLE REGISTRATION (
    RegistrationID VARCHAR2(6) PRIMARY KEY,
    StudentID      VARCHAR2(6) NOT NULL,
    EventID        VARCHAR2(5) NOT NULL,
    CONSTRAINT uq_student_event UNIQUE (StudentID, EventID),
    CONSTRAINT fk_reg_student FOREIGN KEY (StudentID)
        REFERENCES STUDENT(StudentID),
    CONSTRAINT fk_reg_event FOREIGN KEY (EventID)
        REFERENCES EVENT(EventID)
);

CREATE TABLE ATTENDANCE (
    AttendanceID VARCHAR2(6)  PRIMARY KEY,
    StudentID    VARCHAR2(6)  NOT NULL,
    EventID      VARCHAR2(5)  NOT NULL,
    Status       VARCHAR2(10) DEFAULT 'ABSENT'
                              CHECK (Status IN ('PRESENT', 'ABSENT')),
    CONSTRAINT fk_att_student FOREIGN KEY (StudentID)
        REFERENCES STUDENT(StudentID),
    CONSTRAINT fk_att_event FOREIGN KEY (EventID)
        REFERENCES EVENT(EventID)
);

CREATE TABLE EXPENSE (
    ExpenseID VARCHAR2(6) PRIMARY KEY,
    EventID   VARCHAR2(5) NOT NULL,
    Amount    NUMBER      CHECK (Amount > 0),
    CONSTRAINT fk_exp_event FOREIGN KEY (EventID)
        REFERENCES EVENT(EventID)
);

CREATE TABLE EVENT_ANALYTICS (
    AnalyticsID       VARCHAR2(10) PRIMARY KEY,
    EventID           VARCHAR2(5)  UNIQUE,
    SuccessScore      NUMBER CHECK (SuccessScore      BETWEEN 0 AND 100),
    BudgetEfficiency  NUMBER CHECK (BudgetEfficiency  BETWEEN 0 AND 100),
    AttendanceScore   NUMBER CHECK (AttendanceScore   BETWEEN 0 AND 100),
    CONSTRAINT fk_analytics_event FOREIGN KEY (EventID)
        REFERENCES EVENT(EventID)
);

CREATE TABLE FEEDBACK (
    FeedbackID VARCHAR2(6) PRIMARY KEY,
    EventID    VARCHAR2(5) NOT NULL,
    StudentID  VARCHAR2(6) NOT NULL,
    Rating     NUMBER      CHECK (Rating BETWEEN 1 AND 5),
    Comments   CLOB,
    CONSTRAINT fk_fb_event   FOREIGN KEY (EventID)
        REFERENCES EVENT(EventID),
    CONSTRAINT fk_fb_student FOREIGN KEY (StudentID)
        REFERENCES STUDENT(StudentID)
);

CREATE TABLE STUDENT_INTEREST (
    InterestID  VARCHAR2(6)  PRIMARY KEY,
    StudentID   VARCHAR2(6)  NOT NULL,
    InterestTag VARCHAR2(50) NOT NULL,
    CONSTRAINT fk_interest_student FOREIGN KEY (StudentID)
        REFERENCES STUDENT(StudentID)
);


-- ============================================================
-- SECTION 2: ALTER TABLE — Additional Constraints
-- ============================================================

ALTER TABLE DEPARTMENT ADD CONSTRAINT chk_dept
    CHECK (DepartmentName IN (
        'CSE', 'ECE', 'ELEC', 'BIO', 'PHY', 'CHEM', 'MEC', 'CIVIL'
    ));

ALTER TABLE EVENTCATEGORY ADD CONSTRAINT chk_category
    CHECK (CategoryName IN (
        'MATH', 'PAINTING', 'DANCING', 'SINGING', 'GAMES', 'SPORTS', 'BUSINESS'
    ));

ALTER TABLE VENUE ADD CONSTRAINT chk_venue
    CHECK (VenueName IN (
        'Quadrangle',
        'AB5 2nd Floor',
        'AB5 Ground Floor',
        'AB4',
        'AB3',
        'Student Plaza',
        'College Ground'
    ));


-- ============================================================
-- SECTION 3: SEQUENCES
-- ============================================================

-- Sequence for ATTENDANCE primary key (used in mark_attendance.php)
CREATE SEQUENCE SEQ_ATT
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

-- Sequence for EXPENSE primary key (used in add_expense.php)
CREATE SEQUENCE EXPENSE_SEQ
    START WITH 1
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;

-- General EVENT sequence (created in screenshot 2026-04-08 110703)
CREATE SEQUENCE EVENT_SEQ
    START WITH 1001
    INCREMENT BY 1
    NOCACHE
    NOCYCLE;


-- ============================================================
-- SECTION 4: TRIGGERS
-- ============================================================

-- -------------------------------------------------------
-- 4.1  Auto-create ATTENDANCE row after REGISTRATION
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_auto_attendance
AFTER INSERT ON REGISTRATION
FOR EACH ROW
BEGIN
    INSERT INTO ATTENDANCE (AttendanceID, StudentID, EventID)
    VALUES ('AT' || TO_CHAR(SYSDATE, 'HH24MISS'), :NEW.StudentID, :NEW.EventID);
END;
/

-- -------------------------------------------------------
-- 4.2  Prevent registration when venue is at capacity
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_capacity_check
BEFORE INSERT ON REGISTRATION
FOR EACH ROW
DECLARE
    current_count NUMBER;
    max_cap       NUMBER;
BEGIN
    SELECT COUNT(*) INTO current_count
    FROM REGISTRATION
    WHERE EventID = :NEW.EventID;

    SELECT v.Capacity INTO max_cap
    FROM EVENT e
    JOIN VENUE v ON e.VenueID = v.VenueID
    WHERE e.EventID = :NEW.EventID;

    IF current_count >= max_cap THEN
        RAISE_APPLICATION_ERROR(-20001, 'Event Full!');
    END IF;
END;
/

-- -------------------------------------------------------
-- 4.3  Prevent negative or zero expense amount
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_check_expense
BEFORE INSERT OR UPDATE ON EXPENSE
FOR EACH ROW
BEGIN
    IF :NEW.Amount <= 0 THEN
        RAISE_APPLICATION_ERROR(-20002, 'Invalid Expense Amount');
    END IF;
END;
/

-- -------------------------------------------------------
-- 4.4  Prevent total expenses from exceeding budget
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_budget_limit
BEFORE INSERT ON EXPENSE
FOR EACH ROW
DECLARE
    total_exp NUMBER;
    budget    NUMBER;
BEGIN
    SELECT NVL(SUM(Amount), 0) INTO total_exp
    FROM EXPENSE
    WHERE EventID = :NEW.EventID;

    SELECT BudgetAllocated INTO budget
    FROM EVENT
    WHERE EventID = :NEW.EventID;

    IF total_exp + :NEW.Amount > budget THEN
        RAISE_APPLICATION_ERROR(-20003, 'Budget Exceeded!');
    END IF;
END;
/

-- -------------------------------------------------------
-- 4.5  Coordinator must already exist as a student
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_coord_check
BEFORE INSERT ON COORDINATOR
FOR EACH ROW
DECLARE
    cnt NUMBER;
BEGIN
    SELECT COUNT(*) INTO cnt
    FROM STUDENT
    WHERE Name = :NEW.Name;

    IF cnt = 0 THEN
        RAISE_APPLICATION_ERROR(-20004, 'Coordinator must be a student');
    END IF;
END;
/

-- -------------------------------------------------------
-- 4.6  Auto-create EVENT_ANALYTICS row after new EVENT
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_create_analytics
AFTER INSERT ON EVENT
FOR EACH ROW
BEGIN
    INSERT INTO EVENT_ANALYTICS
        (AnalyticsID, EventID, SuccessScore, BudgetEfficiency, AttendanceScore)
    VALUES (
        'AN' || :NEW.EventID,
        :NEW.EventID,
        0,
        0,
        0
    );
END;
/

-- -------------------------------------------------------
-- 4.7  Update BudgetEfficiency in EVENT_ANALYTICS
--      after every EXPENSE insert (statement-level trigger)
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER TRG_UPDATE_BUDGET_EFFICIENCY
AFTER INSERT ON EXPENSE
BEGIN
    UPDATE EVENT_ANALYTICS EA
    SET BudgetEfficiency = (
        SELECT
            CASE
                WHEN E.BudgetAllocated = 0 THEN 0
                ELSE ROUND(
                    (NVL(SUM(EX.Amount), 0) / E.BudgetAllocated) * 100,
                    2
                )
            END
        FROM EVENT E
        LEFT JOIN EXPENSE EX ON E.EventID = EX.EventID
        WHERE E.EventID = EA.EventID
        GROUP BY E.EventID, E.BudgetAllocated
    );
END;
/

-- -------------------------------------------------------
-- 4.8  Update SuccessScore after every FEEDBACK insert
--      (statement-level trigger)
--      Formula: 60% attendance + 30% (100-budget_eff) + 10% avg_rating*10
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER TRG_UPDATE_FEEDBACK
AFTER INSERT ON FEEDBACK
BEGIN
    UPDATE EVENT_ANALYTICS EA
    SET SuccessScore = ROUND(
        (NVL(EA.AttendanceScore, 0) * 0.6) +
        ((100 - NVL(EA.BudgetEfficiency, 0)) * 0.3) +
        (
            NVL(
                (SELECT AVG(F.Rating)
                 FROM FEEDBACK F
                 WHERE F.EventID = EA.EventID),
                0
            ) * 20 * 0.1
        )
    )
    WHERE EXISTS (
        SELECT 1 FROM FEEDBACK F WHERE F.EventID = EA.EventID
    );
END;
/

-- -------------------------------------------------------
-- 4.9  Update AttendanceScore after ATTENDANCE.Status changes
--      (statement-level trigger)
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_update_attendance_score
AFTER UPDATE OF Status ON ATTENDANCE
BEGIN
    UPDATE EVENT_ANALYTICS EA
    SET AttendanceScore = ROUND(
        (
            SELECT COUNT(*)
            FROM ATTENDANCE A
            WHERE A.EventID = EA.EventID
              AND A.Status = 'PRESENT'
        ) * 100 /
        NULLIF(
            (
                SELECT COUNT(*)
                FROM ATTENDANCE A2
                WHERE A2.EventID = EA.EventID
            ),
            0
        )
    )
    WHERE EXISTS (
        SELECT 1 FROM ATTENDANCE A3
        WHERE A3.EventID = EA.EventID
    );
END;
/

-- -------------------------------------------------------
-- 4.10  Prevent feedback from students who were absent
-- -------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_feedback_check
BEFORE INSERT ON FEEDBACK
FOR EACH ROW
DECLARE
    status_val VARCHAR2(10);
BEGIN
    SELECT Status INTO status_val
    FROM ATTENDANCE
    WHERE StudentID = :NEW.StudentID
      AND EventID   = :NEW.EventID;

    IF status_val != 'PRESENT' THEN
        RAISE_APPLICATION_ERROR(-20002, 'Cannot give feedback if absent');
    END IF;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20003, 'No attendance record found');
END;
/


-- ============================================================
-- SECTION 5: VIEWS
-- ============================================================

CREATE OR REPLACE VIEW STUDENT_EVENT_RECOMMENDATION AS
SELECT
    si.StudentID,
    e.EventID,
    e.EventName,
    ec.CategoryName,
    COUNT(r.RegistrationID) AS popularity
FROM EVENT e
JOIN EVENTCATEGORY ec
    ON e.CategoryID = ec.CategoryID
JOIN STUDENT_INTEREST si
    ON si.InterestTag = ec.CategoryName
LEFT JOIN REGISTRATION r
    ON e.EventID = r.EventID
WHERE NOT EXISTS (
    SELECT 1
    FROM REGISTRATION r2
    WHERE r2.StudentID = si.StudentID
      AND r2.EventID   = e.EventID
)
GROUP BY si.StudentID, e.EventID, e.EventName, ec.CategoryName;


-- ============================================================
-- SECTION 6: INSERT — Seed / Reference Data
-- ============================================================

-- ---- ADMIN ----
INSERT INTO "ADMIN" VALUES ('A001', 'Main Admin',  'admin@manipal.edu',  'admin123');
INSERT INTO "ADMIN" VALUES ('A002', 'Event Admin', 'events@manipal.edu', 'event123');

-- ---- DEPARTMENT ----
INSERT INTO DEPARTMENT VALUES ('D001', 'CSE');
INSERT INTO DEPARTMENT VALUES ('D002', 'ECE');
INSERT INTO DEPARTMENT VALUES ('D003', 'ELEC');
INSERT INTO DEPARTMENT VALUES ('D004', 'BIO');
INSERT INTO DEPARTMENT VALUES ('D005', 'PHY');
INSERT INTO DEPARTMENT VALUES ('D006', 'CHEM');
INSERT INTO DEPARTMENT VALUES ('D007', 'MEC');
INSERT INTO DEPARTMENT VALUES ('D008', 'CIVIL');

-- ---- VENUE ----
INSERT INTO VENUE VALUES ('V001', 'Quadrangle',       500);
INSERT INTO VENUE VALUES ('V002', 'AB5 2nd Floor',    200);
INSERT INTO VENUE VALUES ('V003', 'AB5 Ground Floor', 200);
INSERT INTO VENUE VALUES ('V004', 'AB4',              300);
INSERT INTO VENUE VALUES ('V005', 'AB3',              250);
INSERT INTO VENUE VALUES ('V006', 'Student Plaza',    600);
INSERT INTO VENUE VALUES ('V007', 'College Ground',  1000);

-- ---- EVENTCATEGORY ----
INSERT INTO EVENTCATEGORY VALUES ('CAT001', 'MATH');
INSERT INTO EVENTCATEGORY VALUES ('CAT002', 'PAINTING');
INSERT INTO EVENTCATEGORY VALUES ('CAT003', 'DANCING');
INSERT INTO EVENTCATEGORY VALUES ('CAT004', 'SINGING');
INSERT INTO EVENTCATEGORY VALUES ('CAT005', 'GAMES');
INSERT INTO EVENTCATEGORY VALUES ('CAT006', 'SPORTS');
INSERT INTO EVENTCATEGORY VALUES ('CAT007', 'BUSINESS');

-- ---- STUDENT ----
INSERT INTO STUDENT VALUES ('S0001', 'Rahul',  'D001', 2, DATE '2004-05-10');
INSERT INTO STUDENT VALUES ('S0002', 'Ananya', 'D002', 1, DATE '2005-07-12');
INSERT INTO STUDENT VALUES ('S0003', 'Kiran',  'D003', 3, DATE '2003-03-22');
INSERT INTO STUDENT VALUES ('S0004', 'Megha',  'D004', 2, DATE '2004-11-01');
INSERT INTO STUDENT VALUES ('S0005', 'Arjun',  'D007', 4, DATE '2002-09-15');
INSERT INTO STUDENT VALUES ('S0006', 'Sneha',  'D001', 3, DATE '2003-12-18');
INSERT INTO STUDENT VALUES ('S0007', 'Rohit',  'D002', 2, DATE '2004-06-30');
INSERT INTO STUDENT VALUES ('S0008', 'Priya',  'D008', 1, DATE '2005-01-25');

-- ---- COORDINATOR ----
INSERT INTO COORDINATOR VALUES ('C001', 'Rahul',  'D001', 'A001');
INSERT INTO COORDINATOR VALUES ('C002', 'Ananya', 'D002', 'A002');
INSERT INTO COORDINATOR VALUES ('C003', 'Kiran',  'D003', 'A001');

-- ---- EVENT ----
-- MATH
INSERT INTO EVENT VALUES ('MA001', 'Math Quiz',         DATE '2026-04-10', 20000, 'CAT001', 'V002', 'C001');
INSERT INTO EVENT VALUES ('MA002', 'Puzzle Challenge',  DATE '2026-04-11', 20000, 'CAT001', 'V003', 'C001');
-- PAINTING
INSERT INTO EVENT VALUES ('PA001', 'Canvas Art',        DATE '2026-04-12', 30000, 'CAT002', 'V004', 'C002');
INSERT INTO EVENT VALUES ('PA002', 'Graffiti Fest',     DATE '2026-04-13', 30000, 'CAT002', 'V006', 'C002');
-- DANCING
INSERT INTO EVENT VALUES ('DA001', 'Street Dance',      DATE '2026-04-14', 40000, 'CAT003', 'V006', 'C002');
INSERT INTO EVENT VALUES ('DA002', 'Classical Dance',   DATE '2026-04-15', 30000, 'CAT003', 'V001', 'C002');
-- SINGING
INSERT INTO EVENT VALUES ('ST001', 'Solo Singing',      DATE '2026-04-16', 20000, 'CAT004', 'V005', 'C003');
INSERT INTO EVENT VALUES ('ST002', 'Band Night',        DATE '2026-04-17', 50000, 'CAT004', 'V001', 'C003');
-- GAMES
INSERT INTO EVENT VALUES ('GA001', 'BGMI Tournament',   DATE '2026-04-18', 40000, 'CAT005', 'V007', 'C003');
INSERT INTO EVENT VALUES ('GA002', 'Chess Competition', DATE '2026-04-19', 20000, 'CAT005', 'V002', 'C001');
-- SPORTS
INSERT INTO EVENT VALUES ('SP001', 'Football Match',    DATE '2026-04-20', 50000, 'CAT006', 'V007', 'C003');
INSERT INTO EVENT VALUES ('SP002', 'Cricket Match',     DATE '2026-04-21', 60000, 'CAT006', 'V007', 'C003');
-- BUSINESS
INSERT INTO EVENT VALUES ('BU001', 'Startup Pitch',     DATE '2026-04-22', 30000, 'CAT007', 'V003', 'C001');
INSERT INTO EVENT VALUES ('BU002', 'Marketing Battle',  DATE '2026-04-23', 30000, 'CAT007', 'V002', 'C001');

-- ---- STUDENT_INTEREST ----
INSERT INTO STUDENT_INTEREST VALUES ('I001',  'S0001', 'MATH');
INSERT INTO STUDENT_INTEREST VALUES ('I002',  'S0001', 'BUSINESS');
INSERT INTO STUDENT_INTEREST VALUES ('I003',  'S0002', 'PAINTING');
INSERT INTO STUDENT_INTEREST VALUES ('I004',  'S0002', 'DANCING');
INSERT INTO STUDENT_INTEREST VALUES ('I005',  'S0003', 'SINGING');
INSERT INTO STUDENT_INTEREST VALUES ('I006',  'S0003', 'GAMES');
INSERT INTO STUDENT_INTEREST VALUES ('I007',  'S0004', 'DANCING');
INSERT INTO STUDENT_INTEREST VALUES ('I008',  'S0004', 'SINGING');
INSERT INTO STUDENT_INTEREST VALUES ('I009',  'S0005', 'SPORTS');
INSERT INTO STUDENT_INTEREST VALUES ('I010',  'S0005', 'GAMES');
INSERT INTO STUDENT_INTEREST VALUES ('I011',  'S0006', 'BUSINESS');
INSERT INTO STUDENT_INTEREST VALUES ('I012',  'S0006', 'MATH');
INSERT INTO STUDENT_INTEREST VALUES ('I013',  'S0007', 'GAMES');
INSERT INTO STUDENT_INTEREST VALUES ('I014',  'S0007', 'SPORTS');
INSERT INTO STUDENT_INTEREST VALUES ('I015',  'S0008', 'PAINTING');
INSERT INTO STUDENT_INTEREST VALUES ('I016',  'S0008', 'SINGING');

COMMIT;

-- ============================================================
-- END OF SCRIPT
-- ============================================================
