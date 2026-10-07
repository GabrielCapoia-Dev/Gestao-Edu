import { Column, Entity, JoinColumn, ManyToOne, PrimaryColumn } from 'typeorm';
import { MunicipalServerProfile } from './municipal-server-profile.entity';

// US003/CT03-CT05: matrícula possui identidade própria; históricos ficam em tabelas por vigência.
@Entity('matriculas')
export class Matricula {
  @PrimaryColumn({ type: 'char', length: 36 }) uuid!: string;
  @Column({ name: 'server_person_uuid', type: 'char', length: 36 }) serverPersonUuid!: string;
  @Column({ name: 'registration_number', type: 'varchar', length: 40, unique: true }) registrationNumber!: string;
  @Column({ name: 'contract_type_id', type: 'char', length: 36 }) contractTypeId!: string;
  @Column({ name: 'legal_regime_id', type: 'char', length: 36 }) legalRegimeId!: string;
  @Column({ name: 'admitted_on', type: 'date' }) admittedOn!: string;
  @Column({ name: 'probation_completed_on', type: 'date', nullable: true }) probationCompletedOn!: string | null;
  @ManyToOne(() => MunicipalServerProfile, (server) => server.matriculas, { onDelete: 'RESTRICT' })
  @JoinColumn({ name: 'server_person_uuid', referencedColumnName: 'personUuid' }) server!: MunicipalServerProfile;
}
