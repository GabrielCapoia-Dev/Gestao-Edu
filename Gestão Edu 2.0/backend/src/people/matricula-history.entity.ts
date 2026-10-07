import { Column, Entity, Index, PrimaryColumn } from 'typeorm';

// US003/CT05-CT06: alterações da matrícula são eventos temporais, preservados em histórico.
@Entity('matricula_status_periods')
@Index('idx_matricula_status_timeline', ['matriculaUuid', 'validFrom'])
export class MatriculaStatusPeriod {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'matricula_uuid', type: 'char', length: 36 }) matriculaUuid!: string;
  @Column({ name: 'status_code', type: 'varchar', length: 24 }) statusCode!: string;
  @Column({ name: 'valid_from', type: 'date' }) validFrom!: string;
  @Column({ name: 'valid_until', type: 'date', nullable: true }) validUntil!: string | null;
  @Column({ type: 'varchar', length: 500, nullable: true }) reason!: string | null;
}

@Entity('matricula_job_periods')
@Index('idx_matricula_job_timeline', ['matriculaUuid', 'validFrom'])
export class MatriculaJobPeriod {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'matricula_uuid', type: 'char', length: 36 }) matriculaUuid!: string;
  @Column({ name: 'job_uuid', type: 'char', length: 36 }) jobUuid!: string;
  @Column({ name: 'valid_from', type: 'date' }) validFrom!: string;
  @Column({ name: 'valid_until', type: 'date', nullable: true }) validUntil!: string | null;
}

@Entity('matricula_location_periods')
@Index('idx_matricula_location_timeline', ['matriculaUuid', 'validFrom'])
export class MatriculaLocationPeriod {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'matricula_uuid', type: 'char', length: 36 }) matriculaUuid!: string;
  @Column({ name: 'work_location_uuid', type: 'char', length: 36 }) workLocationUuid!: string;
  @Column({ name: 'valid_from', type: 'date' }) validFrom!: string;
  @Column({ name: 'valid_until', type: 'date', nullable: true }) validUntil!: string | null;
}

@Entity('matricula_work_conditions')
@Index('idx_matricula_conditions_timeline', ['matriculaUuid', 'validFrom'])
export class MatriculaWorkCondition {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'matricula_uuid', type: 'char', length: 36 }) matriculaUuid!: string;
  @Column({ name: 'weekly_workload_hours', type: 'decimal', precision: 6, scale: 2 }) weeklyWorkloadHours!: string;
  @Column({ name: 'shift_code', type: 'varchar', length: 40 }) shiftCode!: string;
  @Column({ name: 'valid_from', type: 'date' }) validFrom!: string;
  @Column({ name: 'valid_until', type: 'date', nullable: true }) validUntil!: string | null;
}

@Entity('matricula_career_progressions')
@Index('idx_matricula_progression_timeline', ['matriculaUuid', 'validFrom'])
export class MatriculaCareerProgression {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'matricula_uuid', type: 'char', length: 36 }) matriculaUuid!: string;
  @Column({ name: 'career_band_uuid', type: 'char', length: 36 }) careerBandUuid!: string;
  @Column({ name: 'valid_from', type: 'date' }) validFrom!: string;
  @Column({ name: 'reference_document', type: 'varchar', length: 180, nullable: true }) referenceDocument!: string | null;
}
