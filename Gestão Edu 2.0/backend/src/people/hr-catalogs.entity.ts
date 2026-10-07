import { Column, Entity, Index, PrimaryColumn } from 'typeorm';

// US003/CT04-CT05: catálogos separados evitam valores jurídicos/cargos duplicados em matrículas.
@Entity('employment_contract_types')
export class EmploymentContractType {
  @PrimaryColumn({ type: 'char', length: 36 }) id!: string;
  @Column({ type: 'varchar', length: 40, unique: true }) code!: string;
  @Column({ type: 'varchar', length: 120 }) label!: string;
  @Column({ type: 'boolean', default: true }) active!: boolean;
}

@Entity('legal_regimes')
export class LegalRegime {
  @PrimaryColumn({ type: 'char', length: 36 }) id!: string;
  @Column({ type: 'varchar', length: 40, unique: true }) code!: string;
  @Column({ type: 'varchar', length: 120 }) label!: string;
  @Column({ type: 'boolean', default: true }) active!: boolean;
}

@Entity('jobs')
export class Job {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ type: 'varchar', length: 160 }) name!: string;
  @Column({ type: 'text', nullable: true }) description!: string | null;
  @Column({ type: 'boolean', default: true }) active!: boolean;
}

@Entity('career_bands')
@Index('uq_career_band_job_code', ['jobUuid', 'code'], { unique: true })
export class CareerBand {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'job_uuid', type: 'char', length: 36 }) jobUuid!: string;
  @Column({ type: 'varchar', length: 40 }) code!: string;
  @Column({ type: 'varchar', length: 120 }) label!: string;
  @Column({ type: 'boolean', default: true }) active!: boolean;
}

@Entity('work_locations')
export class WorkLocation {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ type: 'varchar', length: 180 }) name!: string;
  @Column({ type: 'boolean', default: true }) active!: boolean;
}

@Entity('matricula_statuses')
export class MatriculaStatus {
  @PrimaryColumn({ type: 'varchar', length: 24 }) code!: string;
  @Column({ type: 'varchar', length: 80 }) label!: string;
  @Column({ type: 'boolean', default: false }) terminal!: boolean;
}
