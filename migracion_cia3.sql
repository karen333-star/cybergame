-- CyberGame CIA3 migration
-- Adds confidentiality / integrity / accessibility breakdown while keeping legacy CIA columns intact.

USE CYBERGAME;

START TRANSACTION;

ALTER TABLE partidas
  ADD COLUMN c_inicial TINYINT UNSIGNED NULL AFTER cia_inicial,
  ADD COLUMN i_inicial TINYINT UNSIGNED NULL AFTER c_inicial,
  ADD COLUMN a_inicial TINYINT UNSIGNED NULL AFTER i_inicial,
  ADD COLUMN c_final TINYINT UNSIGNED NULL AFTER cia_final,
  ADD COLUMN i_final TINYINT UNSIGNED NULL AFTER c_final,
  ADD COLUMN a_final TINYINT UNSIGNED NULL AFTER i_final;

ALTER TABLE eventos_partida
  ADD COLUMN c_antes TINYINT UNSIGNED NULL AFTER cia_antes,
  ADD COLUMN i_antes TINYINT UNSIGNED NULL AFTER c_antes,
  ADD COLUMN a_antes TINYINT UNSIGNED NULL AFTER i_antes,
  ADD COLUMN c_despues TINYINT UNSIGNED NULL AFTER cia_despues,
  ADD COLUMN i_despues TINYINT UNSIGNED NULL AFTER c_despues,
  ADD COLUMN a_despues TINYINT UNSIGNED NULL AFTER i_despues;

ALTER TABLE impactos_opcion
  ADD COLUMN delta_c_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_cia_base,
  ADD COLUMN delta_i_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_c_base,
  ADD COLUMN delta_a_base SMALLINT NOT NULL DEFAULT 0 AFTER delta_i_base;

-- Backfill legacy options so current balance stays stable until each option is tuned individually.
UPDATE impactos_opcion
SET delta_c_base = delta_cia_base,
    delta_i_base = delta_cia_base,
    delta_a_base = delta_cia_base
WHERE delta_c_base = 0 AND delta_i_base = 0 AND delta_a_base = 0;

COMMIT;
