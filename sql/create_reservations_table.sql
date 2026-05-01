CREATE TABLE `reservations` (
  `bookingID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `startTime` datetime NOT NULL,
  `endTime` datetime NOT NULL,
  `equipmentID` int(11) NOT NULL,
  `status` enum('ready','ongoing','terminated') NOT NULL DEFAULT 'ready',
  PRIMARY KEY (`bookingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `reservations` (`bookingID`, `userID`, `startTime`, `endTime`, `equipmentID`, `status`) VALUES
(1, 1, '2026-05-01 09:00:00', '2026-05-01 11:00:00', 1, 'ongoing'),
(2, 2, '2026-05-02 10:00:00', '2026-05-02 12:00:00', 2, 'ready'),
(3, 3, '2026-05-03 14:00:00', '2026-05-03 16:30:00', 3, 'terminated');