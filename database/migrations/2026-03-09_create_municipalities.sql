-- Create municipalities table with postal codes

CREATE TABLE IF NOT EXISTS municipalities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  region VARCHAR(120) NOT NULL,
  postal_code VARCHAR(20) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_municipalities_region_name (region, name),
  KEY idx_municipalities_region (region)
) ENGINE=InnoDB;

-- Insert municipalities by region (Philippine municipalities with postal codes)

-- NCR
INSERT INTO municipalities (name, region, postal_code) VALUES
('Caloocan', 'NCR', '1400'),
('Las Piñas', 'NCR', '1740'),
('Makati', 'NCR', '1200'),
('Malabon', 'NCR', '1401'),
('Mandaluyong', 'NCR', '1550'),
('Manila', 'NCR', '1000'),
('Marikina', 'NCR', '1800'),
('Muntinlupa', 'NCR', '1780'),
('Navotas', 'NCR', '1402'),
('Parañaque', 'NCR', '1700'),
('Pasay', 'NCR', '1300'),
('Pasig', 'NCR', '1600'),
('Quezon City', 'NCR', '1100'),
('San Juan', 'NCR', '1500'),
('Taguig', 'NCR', '1630'),
('Valenzuela', 'NCR', '1440'),
('Pateros', 'NCR', '1700'),

-- CAR
('Baguio', 'CAR', '2600'),
('Benguet', 'CAR', '2601'),
('Ifugao', 'CAR', '3601'),
('Kalinga', 'CAR', '3800'),
('Mountain Province', 'CAR', '2626'),
('Nueva Vizcaya', 'CAR', '3700'),
('Quirino', 'CAR', '3800'),

-- Region I
('Batanes', 'Region I', '3700'),
('Cagayan', 'Region I', '3700'),
('Ilocos Norte', 'Region I', '2900'),
('Ilocos Sur', 'Region I', '2700'),
('La Union', 'Region I', '2500'),

-- Region II
('Albay', 'Region II', '4800'),
('Batanes', 'Region II', '3700'),
('Catanduanes', 'Region II', '4800'),
('Isabela', 'Region II', '3300'),
('Nueva Vizcaya', 'Region II', '3700'),
('Quirino', 'Region II', '3800'),

-- Region III
('Aurora', 'Region III', '3200'),
('Bataan', 'Region III', '2100'),
('Bulacan', 'Region III', '3000'),
('Nueva Ecija', 'Region III', '3100'),
('Pampanga', 'Region III', '2000'),
('Tarlac', 'Region III', '2300'),
('Zambales', 'Region III', '2200'),

-- Region IV-A
('Cavite', 'Region IV-A', '4100'),
('Laguna', 'Region IV-A', '4009'),
('Quezon', 'Region IV-A', '4325'),
('Rizal', 'Region IV-A', '1900'),
('Batangas', 'Region IV-A', '4200'),

-- Region IV-B
('Antique', 'Region IV-B', '5700'),
('Mindoro Occidental', 'Region IV-B', '5100'),
('Mindoro Oriental', 'Region IV-B', '5200'),
('Palawan', 'Region IV-B', '5300'),

-- Region V
('Albay', 'Region V', '4800'),
('Camarines Norte', 'Region V', '4600'),
('Camarines Sur', 'Region V', '4400'),
('Catanduanes', 'Region V', '4800'),
('Sorsogon', 'Region V', '4700'),

-- Region VI
('Aklan', 'Region VI', '5600'),
('Antique', 'Region VI', '5700'),
('Capiz', 'Region VI', '5800'),
('Guimaras', 'Region VI', '5044'),
('Iloilo', 'Region VI', '5000'),
('Negros Occidental', 'Region VI', '6100'),

-- Region VII
('Bohol', 'Region VII', '6300'),
('Cebu', 'Region VII', '6000'),
('Negros Oriental', 'Region VII', '6200'),
('Siquijor', 'Region VII', '6230'),

-- Region VIII
('Eastern Samar', 'Region VIII', '6800'),
('Leyte', 'Region VIII', '6500'),
('Northern Samar', 'Region VIII', '6408'),
('Samar', 'Region VIII', '6700'),
('Southern Leyte', 'Region VIII', '6520'),

-- Region IX
('Misamis Occidental', 'Region IX', '8700'),
('Misamis Oriental', 'Region IX', '8700'),
('Zamboanga del Norte', 'Region IX', '8800'),
('Zamboanga del Sur', 'Region IX', '9000'),
('Zamboanga Sibugay', 'Region IX', '8900'),

-- Region X
('Bukidnon', 'Region X', '8700'),
('Lanao del Norte', 'Region X', '9200'),
('Misamis Occidental', 'Region X', '8700'),
('Misamis Oriental', 'Region X', '8700'),

-- Region XI
('Compostela Valley', 'Region XI', '8200'),
('Davao del Norte', 'Region XI', '8100'),
('Davao del Sur', 'Region XI', '8000'),
('Davao Oriental', 'Region XI', '8200'),

-- Region XII
('Cotabato', 'Region XII', '9600'),
('Sarangani', 'Region XII', '9800'),
('South Cotabato', 'Region XII', '9500'),
('Sultan Kudarat', 'Region XII', '9800'),

-- Region XIII
('Agusan del Norte', 'Region XIII', '8600'),
('Agusan del Sur', 'Region XIII', '8500'),
('Dinagat Islands', 'Region XIII', '8400'),
('Surigao del Norte', 'Region XIII', '8400'),
('Surigao del Sur', 'Region XIII', '8300'),

-- BARMM
('Lanao del Sur', 'BARMM', '9300'),
('Maguindanao', 'BARMM', '9600'),
('Sulu', 'BARMM', '7400'),
('Tawi-Tawi', 'BARMM', '7500');
